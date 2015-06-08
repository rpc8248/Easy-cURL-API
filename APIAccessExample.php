<?php

// Client IDs and API keys. Generate these on Google's Dev Console.
$OAUTH2_CLIENT_ID = '<Your client id>';
$OAUTH2_CLIENT_SECRET = '<Your client secret>';
$API_BROWSER_KEY = '<Your api browser key>';
$API_SERVER_KEY = '<Your api server key>';

// This example gets all the videos from a playlist
// Example playlist:
// https://www.youtube.com/playlist?list=PLFgquLnL59alCl_2TQvOiD5Vgm1hCaGSI
$playlist_id = 'PLFgquLnL59alCl_2TQvOiD5Vgm1hCaGSI';

// Import the API accessor
require_once 'APIAccess.php';

// Create the object that will be used to access the API
$client = new APIAccess("Google");

// Prompt the user to proceed if they haven't already
if(!$client->userInitiated())
{
    $htmlBody = $client->needOauth($OAUTH2_CLIENT_ID);
}

// Otherwise, authenticate the user and continue
else if($client->authenticate($OAUTH2_CLIENT_ID,$OAUTH2_CLIENT_SECRET))
{
    try {
        // QUERIES GO HERE
        
        // Example: Get playlist items with appropriate parameters
        $url = 'https://www.googleapis.com/youtube/v3/playlistItems';
        $params['part']='snippet';
        $params['maxResults']='50';
        
        //playlistId would likely be obtained via other means
        $params['playlistId']=$playlist_id;
        
        // Get initial result from apiCall
        $result = array();
        $result[] = $client->apiCall($url,$params);

        // Step forward through responses, adding them to the overall result
        while($response = $client->getNextResponse())
        {
            $result[] = $response;
        }
        
        /*
        // Step backward through responses, adding them to the overall result
        while($response = $client->getPrevResponse())
        {
            $result[] = $response;
        }
        */
        
        // Take the final results and "HTML-ize" them for viewing
        $htmlBody = $client->view($result);
    }
    
    // Error handling
    catch (Google_ServiceException $e) {
        $htmlBody.= sprintf('<p>A service error occurred: <code>%s</code></p>',
    htmlspecialchars($e->getMessage()));
    } catch (Google_Exception $e) {
        $htmlBody.= sprintf('<p>An client error occurred: <code>%s</code></p>',
    htmlspecialchars($e->getMessage()));
    }
}
?>

<?=$htmlBody?>