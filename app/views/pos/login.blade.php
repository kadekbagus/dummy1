
@extends('pos.layouts.default')
@section('content')
<div class="main-container">
  <div class="page-signin" ng-controller="loginCtrl">
    <div class="signin-header">
      <section class="logo text-center">
        <h4>ORBIT KASIR</h4>
        <img src="{{ URL::asset('templatepos/images/orbit-logo.png') }}" alt="Orbit Logo" />
      </section>
    </div>

        <div class="signin-body">
               <div class="container">
                   <div class="form-container">
                       <div class="orbit-component alert ng-isolate-scope alert-danger alert-dismissable" ng-repeat="alert in signin.alerts" ng-class="{active: alert.active}">
                           <span class="close-button" ng-click="signin.alertDismisser($index)"><i class="fa fa-times"></i></span>
                           <span> <%alert.text %></span>
                       </div>
                       <form name="signform" class="form-horizontal">
                           <fieldset>
                               <div class="form-group">
                                   <span class="glyphicon glyphicon-envelope"></span>
                                   <input type="text" name="username" class="orbit-component form-control input-lg input-round text-center" placeholder="ID" ng-model="login.username" required />
                               </div>
                               <div class="form-group">
                                   <span class="glyphicon glyphicon-lock"></span>
                                   <input ng-disabled="signform.username.$invalid" type="password" name="password" class="orbit-component form-control input-lg input-round text-center" placeholder="Password" ng-model="login.password" required />
                               </div>
                               <div class="form-group">
                                   <button ng-disabled="signform.$invalid" class="btn btn-primary btn-lg btn-round btn-block text-center" data-ng-click="loginFn()" type="submit">Masuk</button>
                               </div>
                           </fieldset>
                       </form>
                   </div>
               </div>
        </div>
  </div>
</div>
@stop

