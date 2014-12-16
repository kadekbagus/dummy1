/**
 * @author agung@dominopos.com
 */

"use strict";


var app = angular.module('app', [], function($interpolateProvider) {
    $interpolateProvider.startSymbol('<%');
    $interpolateProvider.endSymbol('%>');

}).controller('loginCtrl', ['$scope', function($scope) {
    $scope.datapassword = '11';

}
]);

