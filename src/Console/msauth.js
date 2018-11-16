// This remaining code is taken from the MSAL.js example

// Initialize application
var userAgentApplication = new Msal.UserAgentApplication(
    msalconfig.clientID,
    null,
    // Call our authentication service login function with the appropriate callback to redirect successful logins to /
    loginCallback,
    {
        redirectUri: msalconfig.redirectUri
    });

// Initialize application
var userAgentApplication = new Msal.UserAgentApplication(msalconfig.clientID, null, loginCallback, {
    redirectUri: msalconfig.redirectUri
});

//Previous version of msal uses redirect url via a property
if (userAgentApplication.redirectUri) {
    userAgentApplication.redirectUri = msalconfig.redirectUri;
}

console.log('inside window.onload');
// If page is refreshed, continue to display user info
if (!userAgentApplication.isCallback(window.location.hash) && window.parent === window && !window.opener) {
    console.log('window location hash is NOT a valid callback, checking for authenticated user ' + window.location.hash);
    var user = userAgentApplication.getUser();
    if (user) {
        console.log('got valid user, can continue loading load.js for remaining app pieces');
        //console.log(user);

        document.write('<script src="load.js" type="text/javascript"></script>');
    } else {
        console.log('did not get valid user, should redirect for authentication');
        userAgentApplication.loginRedirect(APIScopes);
    }
} else {
    //console.log('window location hash is a valid callback ' + window.location.hash);
    console.log('in case msal failed to redirect a newly authenticated user, we are doing it now ' + msalconfig.redirectUri)
    window.location.href = msalconfig.redirectUri;
}

/**
 * Callback method from sign-in: if no errors, call getToken() to show results.
 * @param {string} errorDesc - If error occur, the error message
 * @param {object} token - The token received from sign-in
 * @param {object} error - The error string
 * @param {string} tokenType - The token type: For loginRedirect, tokenType = "id_token". For acquireTokenRedirect, tokenType:"access_token".
 */
function loginCallback(errorDesc, token, error, tokenType) {
    console.log('inside loginCallback, does this ever actually trigger?');
    if (errorDesc) {
        console.log(msal.authority);
        console.log(error);
        console.log(errorDesc);
    } else {
        //getToken();
    }
    console.log('usually this fires before microsofts library redirects us back to ourselves without the idtoken');
}

/**
 * Sign out the user
 */
function signOut() {
    userAgentApplication.logout();
}
