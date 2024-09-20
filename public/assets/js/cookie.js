function setCookie(c_name, value, minutes = 15) {
    let c_value = encodeURIComponent(value);
    let date = new Date();
    date.setTime(date.getTime() + (minutes * 60 * 1000));
    let expires = "; expires=" + date.toUTCString();
    document.cookie = c_name + "=" + c_value + expires + "; path=/";
}

function getCookie(c_name) {
    let name = encodeURIComponent(c_name) + "=";
    let decodedCookie = decodeURIComponent(document.cookie);
    let cookiesArray = decodedCookie.split(';');
    for (let i = 0; i < cookiesArray.length; i++) {
        let cookie = cookiesArray[i].trim();
        if (cookie.indexOf(name) === 0) {
            return cookie.substring(name.length, cookie.length);
        }
    }
    return null;
}

function deleteCookie(c_name) {
    setCookie(c_name, "", -1);
}



jQuery.cookie = function (key, value, options) {

    // key and at least value given, set cookie...
    if (arguments.length > 1 && String(value) !== "[object Object]") {
        options = jQuery.extend({}, options);

        if (value === null || value === undefined) {
            options.expires = -1;
        }

        if (typeof options.expires === 'number') {
            let days = options.expires, t = options.expires = new Date();
            t.setDate(t.getDate() + days);
        }

        value = String(value);

        // Extract assignment of document.cookie
        let cookieString = [
            encodeURIComponent(key), '=',
            options.raw ? value : encodeURIComponent(value),
            options.expires ? '; expires=' + options.expires.toUTCString() : '', // use expires attribute, max-age is not supported by IE
            options.path ? '; path=' + options.path : '',
            options.domain ? '; domain=' + options.domain : '',
            options.secure ? '; secure' : ''
        ].join('');

        document.cookie = cookieString;

        return cookieString;
    }

    // key and possibly options given, get cookie...
    options = value || {};
    let result;
    let decode = options.raw ? function (s) { return s; } : decodeURIComponent;

    result = new RegExp('(?:^|; )' + encodeURIComponent(key) + '=([^;]*)').exec(document.cookie);

    return result ? decode(result[1]) : null;
};
