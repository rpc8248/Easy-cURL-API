<?php
/**
 * Contains API calls and handles GET and POST requests
 * using cURL. Currently, only Google/YouTube is supported, but other 
 * sites such as Twitter and Facebook should be supported eventually.
 */
class APIAccess
{
    // Class variables. Some of these are tailored for Google.
    // In the future, a class GoogleAPIAccess may exist that
    // extends APIAccess. This will happen when more APIs are
    // implemented and the similarities between them are made
    // more clear.
    private $curl_handle;
    private $scopes;
    private $redirect;
    private $response_type;
    private $access_type;
    private $currUrl;
    private $prevToken;
    private $nextToken;
    private $tokenQuery;
    
    // Constructor for APIAccess class
    //  A factory design pattern may be considered.
    public function __construct($site)
    {
        if($site == "Google")
        {
            // Initiate cURL.
            $this->curl_handle = curl_init();
            
            // Set up initial URI for authentication.
            $this->authUrl = 'https://accounts.google.com/o/oauth2/auth';
            $this->scopes = 'https://www.googleapis.com/auth/youtube';
            $this->redirect = filter_var('http://' . $_SERVER['HTTP_HOST'] . 
                $_SERVER['PHP_SELF'], FILTER_SANITIZE_URL);
            $this->response_type = 'code';
            $this->access_type = 'online';

            $this->tokenQuery = '&pageToken=';
        }
    }
    
    /**
     * Set scope URL.
     */
    public function setScope($url)
    {
        $this->$scopes = $url;
    }
    
    /**
     * Set redirect URL.
     */
    public function setRedirect($url)
    {
        $this->$redirect = $url;
    }
    
    /**
     * Returns true if the user has decided to proceed with
     * the login process, and false otherwise. Currently, it's
     * based on a temporary string query that's removed once
     * a code from Google is obtained.
     */
    public function userInitiated()
    {
        return isset($_GET['token_requested'])
            || isset($_GET['code'])
            || isset($_GET['token']);
    }
    
    /**
     * Entire authentication process/oauth2 flow in one function.
     * 
     */
    public function authenticate($OAUTH2_CLIENT_ID, $OAUTH2_CLIENT_SECRET)
    {
        if(!isset($_GET['code']))
        {
        $url = $this->authUrl.'?'.
          'client_id='.$OAUTH2_CLIENT_ID.
          '&redirect_uri='.$this->redirect.
          '&scope='.$this->scopes.
          '&response_type='.$this->response_type.
          '&access_type='.$this->access_type;
          
          header("Location: ".$url);
        }
        else if(isset($_GET['code']) && !isset($_SESSION['token'])) {
            $url = 'https://www.googleapis.com/oauth2/v3/token'.
                '?code='.$_GET['code'].
                '&client_id='.$OAUTH2_CLIENT_ID.
                '&client_secret='.$OAUTH2_CLIENT_SECRET.
                '&redirect_uri='.filter_var('http://' . $_SERVER['HTTP_HOST'] 
                    . $_SERVER['PHP_SELF'], FILTER_SANITIZE_URL).
                '&grant_type='.'authorization_code';
                
            curl_setopt($this->curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->curl_handle, CURLOPT_USERAGENT, 
                'All In One YouTube Integration');
            curl_setopt($this->curl_handle, CURLOPT_POST, 1);
            curl_setopt($this->curl_handle, CURLOPT_URL, $url);
            $result = curl_exec($this->curl_handle);
            
            if (true || strval($_SESSION['state']) !== strval($_GET['state'])) {
                die("The session state did not match.
                    <a href='$this->redirect'>Click here</a> to try again.");
            }

            $_SESSION['token'] = $result;
            header('Location: ' . $redirect);
        }
        if(isset($_SESSION['token']))
        {
            // CURL Header
            $access = json_decode($_SESSION['token'])->access_token;
            $header = array();
            $header[] = 'Authorization: Bearer '.$access;
            curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, $header);
            curl_setopt($this->curl_handle, CURLOPT_POST, 0);
            curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, true);
            return $header;
        }
        else return false;
    }
    
    /**
     * Set up a call for an API based on a URL and query parameters,
     * perform the call and return the first result set.
     */
    public function apiCall($url, $params = null)
    {
        $this->currUrl = $url . $this->parseParams($params);
        curl_setopt($this->curl_handle, CURLOPT_URL, $this->currUrl);
        
        $result = json_decode(curl_exec($this->curl_handle),true);
        
        // Usually happens because the session expires.
        // Restart the session if that's the case.
        if($result['error'])
        {
            $_GET['code'] = array();
            session_destroy();
            header("Location: ".$this->redirect);
        }
        
        $this->nextToken = $result['nextPageToken'];
        
        if(count($result) == 0)
        {
            return false;
        }
        return $result;
    }
    
    /**
     * Helper function that parses the query parameters.
     */
    private function parseParams($params)
    {
        if($params == null)
            return null;
        
        $query = '?';
        while($param = current($params))
        {
            $query .= key($params).'='.$param.'&';
            next($params);
        }
        return rtrim($query,'&');
    }
    
    /**
     * Get the next result set of the most recent API call (if any).
     *
     * Ex. The vast majority of Google's API calls have an upper bound 
     * of 50 items per result. In each result set are tokens that can
     * be used to access the adjacent pages if there are more than
     * 50 items in the entire result set.
     */
    public function getNextResponse()
    {
        if($this->nextToken == null)
        {
            return false;
        }
        
        curl_setopt($this->curl_handle, CURLOPT_URL, 
            $this->currUrl.$this->tokenQuery.$this->nextToken);
        $result = json_decode(curl_exec($this->curl_handle),true);
        $this->prevToken = $result['prevPageToken'];
        $this->nextToken = $result['nextPageToken'];
        
        return $result;
    }
    
    /**
     * Get the previous result set of the most recent API call (if any).
     *
     * Ex. The vast majority of Google's API calls have an upper bound 
     * of 50 items per result. In each result set are tokens that can
     * be used to access the adjacent pages if there are more than
     * 50 items in the entire result set.
     *
     * NOTE: getNextResponse and getPrevResponse may turn into one function,
     * "getResponse($offset)", where offset is the number of pages to 
     * turn.
     */
    public function getPrevResponse()
    {
        if($this->prevToken == null)
        {
            return false;
        }
        
        curl_setopt($this->curl_handle, CURLOPT_URL, 
            $this->currUrl.$this->tokenQuery.$this->prevToken);
        $result = json_decode(curl_exec($this->curl_handle),true);
        $this->prevToken = $result['prevPageToken'];
        $this->nextToken = $result['nextPageToken'];
        
        return $result;
    }
    
    /**
     * Close the cURL connection
     */
    public function closeConnection()
    {
        curl_close($curl_handle);
    }
    
    /**
     * View the result set to display on the page.
     *
     * Currently, the json_encoded text is simply displayed on the page.
     * This will likely end up in its own php file dedicated to the view.
     * The project will most likely end up following the MVC model.
     */
    public function view($result)
    {
        echo '<pre>';
        echo "Results:
        
        ";
        print_r($result);
        echo '
        
Json Output:
        
        </pre>';
        die(json_encode($result));
    }
    
    /**
     * If the user hasn't proceeded, they will be prompted that they
     * need to grant access. This function sets up the preliminary
     * URL and sets the state.
     */
    public function needOauth($OAUTH2_CLIENT_ID, $force = false)
    {
        if($force)
        {
            $approval = 'force';
        }
        else $approval = 'auto';
    
        // If the user hasn't authorized the app, initiate the OAuth flow
        $_SESSION['state'] = mt_rand();
        
        $authUrl = $this->authUrl.'?'.
          'redirect_uri='.$this->redirect.
          '&scope='.$this->scopes.
          '&response_type='.$this->response_type.
          '&access_type='.$this->access_type.
          '&approval_prompt='.$approval.
          '&client_id='.$OAUTH2_CLIENT_ID.
          '&state='.$_SESSION['state'].
          '&token_requested='.'1';
        
        return <<<END
        <div class="modal fade">
        <div class="modal-dialog">
        <div class="modal-content">
        <div class="modal-header">
        <button type="button" class="close" 
            data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Authorization Required</h4>
        </div>
        <div class="modal-body">
        <p>You need to login to Youtube.</p>
        </div>
        <div class="modal-footer">
        <button type="button" class="btn btn-default" 
            data-dismiss="modal">Close</button>
        <a href="$authUrl" class="btn btn-primary">Proceed</a>
        </div>
        </div>
        </div>
        </div>
END;
    }
}
?>
