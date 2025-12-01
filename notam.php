
<?php
    //Make a simple API call and return the result

    //setup variables to use in the API call
    $API_KEY ="eyJjdCI6IkZh"; //Replace this with your own API KEY
    $API_PASSWORD ="V1RENDhhMmp4Z05XWVk4dHgxMHo4VHNreGViYW1GUEZIRTB4VXQ1QXZNK3p4QjR3N21BUjBcL3ZBRDBrdWd4NGF1VVB6dStkSUd6Y05ObXFONHQyNTUrMHI4Y2hPSVZ0d1JiZENpOTY5QT0iLCJpdiI6IjI0OGIxMzJlYmI2NjZiODMxZWYyMjdkYmIyMmQ4NDcxIiwicyI6IjQ1ZDM5NTQxZGVkOWQxZDgifQ=="; //Replace this with your own API PASSWORD
    $API_ACCESS_TOKEN ="11aa57df6408744fbf70c89fe8d27f51c8b933dc"; //Replace this with your own API ACCESS TOKEN
    $API_BASE_URL="https://www.avdelphi.com/api/1.0/";
    $API_ENDPOINT="airframes";
    $API_COMMAND="info";
    $REGISTRATION="HB-JNB";
    $ITEMLIST="type_short;type_long;manufacturer";

    $targetUrl=$API_BASE_URL . $API_ENDPOINT .".svc?api_key=$API_KEY&api_access_token=$API_ACCESS_TOKEN&cmd=$API_COMMAND&registration=$REGISTRATION&itemlist=$ITEMLIST";

    //make the call and get the result
    $RESULT=file_get_contents($targetUrl);

    //print the result
    echo print_r($RESULT,true);

?>

