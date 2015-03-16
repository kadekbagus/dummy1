/**
 * version
 *  version of the application
 * @author Agung (agung.julisman@yahoo.com)
 */

"use strict";

define([

], function () {
  var version = {};
    version.timestamp       = moment().unix();
    version.posVersion      = "0.9";
    version.posBuildNumber  = "0";
  return version;
});