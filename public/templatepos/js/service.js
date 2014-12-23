/**
 * Created with JetBrains PhpStorm.
 * User: julisman
 * Date: 1/8/14
 * Time: 3:18 PM
 * http://blog.julisman.com
 */
'use strict';

define([
    'app'
], function (app) {
    app.factory('serviceAjax' , ['$http', '$q',function($http,$q){

        return{
            getDataFromServer : function(){
                var params = '';
                for(var i = 0 ; i < arguments.length; i++){
                    params += arguments[i];
                }
                return $http.get(app['baseUrlServer'] + params)
                    .then(function(response){
                        if (response.data) {
                            return response.data;
                        } else {
                            // invalid response
                            return $q.reject(response.data);
                        }
                    },function(response){
                        // invalid response
                        return $q.reject(response.data);
                    });
            },

            posDataToServer : function(){
                var params = '',
                    data   = {};

                for(var i = 0 ; i < arguments.length; i++){
                    if(typeof arguments[i] === 'object'){
                        data = arguments[i];
                    }else{
                        params += arguments[i]+'/';
                    }
                }
                return  $http.post(app['baseUrlServer'] + params, $.param(data))
                    .then(function(response){
                        if (response.data) {
                            return response.data;
                        } else {
                            // invalid response
                            return $q.reject(response.data);
                        }
                    },function(response){
                        // invalid response
                        return $q.reject(response.data);
                    });
            }
        }
    }]);

});