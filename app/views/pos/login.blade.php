<!-- app/views/login.blade.php -->


	{{--{{ Form::open(array('url' => 'login')) }}
		<h1>Login</h1>

		<!-- if there are login errors, show them here -->
		<p>
			{{ $errors->first('email') }}
			{{ $errors->first('password') }}
		</p>

		<p>

			{{ Form::label('email', 'Email Address') }}
			{{ Form::text('email', Input::old('email'), array('placeholder' => 'awesome@awesome.com')) }}
		</p>

		<p>
			{{ Form::label('password', 'Password') }}
			{{ Form::password('password') }}
		</p>

		<p>{{ Form::submit('Submit!') }}</p>
	{{ Form::close() }}--}}
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
          <div class="orbit-component alert ng-isolate-scope alert-danger alert-dismissable" ng-repeat="alert in signIn.alerts" ng-class="{active: alert.active}">
            <span class="close-button" ng-click="signIn.alertDismisser($index)"><i class="fa fa-times"></i></span>

          </div>
          <% datapassword %>
          {{ Form::open(array('url' => 'app/v1/pos/login' ,'class' => 'form-horizontal' )) }}
          	 <fieldset>
                       	<!-- if there are login errors, show them here -->
                          <p>
                          	{{ $errors->first('email') }}
                          	{{ $errors->first('password') }}
                          </p>
                          <div class="form-group">
                            <span class="glyphicon glyphicon-envelope"></span>
                            <input type="email" name="username " class="orbit-component form-control input-lg input-round text-center" placeholder="ID" ng-model="signIn.email" required />
                          </div>
                          <div class="form-group">
                            <span class="glyphicon glyphicon-lock"></span>
                            <input ng-disabled="signInForm.emailInput.$invalid" type="password" name="password" class="orbit-component form-control input-lg input-round text-center" placeholder="Password" ng-model="signIn.password" required />
                          </div>
                          <div class="form-group">
                            <button ng-disabled="signInForm.$invalid" ng-click="signIn.validate()" class="btn btn-primary btn-lg btn-round btn-block text-center" type="submit">Log in</button>
                          </div>
             </fieldset>

            {{ Form::close() }}



        </div>
      </div>
    </div>
  </div>
</div>
@stop

