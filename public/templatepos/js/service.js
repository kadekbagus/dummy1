/**
 * service
 *  connect to server via http request get and post
 *  @author agung.julisman@yahoo.com
 */

"use strict";

define([
    'app',
    'config'
], function (app,config) {
    app.factory('serviceAjax' , ['$http', '$q',function($http,$q){

        return{
            getDataFromServer : function(){
                var params = '';
                for(var i = 0 ; i < arguments.length; i++){
                    params += arguments[i];
                }
                return $http.get(config['baseUrlServer'] + params)
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
                        params += arguments[i];
                    }
                }
                return  $http.post(config['baseUrlServer'] + params, $.param(data))
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