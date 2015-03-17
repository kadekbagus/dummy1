/**
 * version
 *  version of the application
 * @author Agung (agung.julisman@yahoo.com)
 */

"use strict";

define([

], function () {
    var version = {};
    version.timestamp    = moment().unix();
    version.version      = "0.9";
    version.buildNumber  = "0";
    version.buildDate    = "0";

    return version;
});