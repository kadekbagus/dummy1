<!-- app/views/login.blade.php -->

<!doctype html>
<html>
<head>
<link rel="stylesheet" href=" {{ URL::asset('templatepos/css/main.css') }} ">
 <link rel="stylesheet" href="bower_components/font-awesome/css/font-awesome.min.css">
	<title>LOG IN KASIR</title>
</head>
<body>

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
<div class="main-container">
  <div class="page-signin" ng-controller="SignInCtrl">
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
          <form name="signInForm" class="form-horizontal">
            <fieldset>
              <div class="form-group">
                <span class="glyphicon glyphicon-envelope"></span>
                <input type="email" name="emailInput" class="orbit-component form-control input-lg input-round text-center" placeholder="ID" ng-model="signIn.email" required />
              </div>
              <div class="form-group">
                <span class="glyphicon glyphicon-lock"></span>
                <input ng-disabled="signInForm.emailInput.$invalid" type="password" name="passwordInput" class="orbit-component form-control input-lg input-round text-center" placeholder="Password" ng-model="signIn.password" required />
              </div>
              <div class="form-group">
                <!-- <a ng-disabled="signInForm.$invalid" ng-click="signIn.validate()" class="btn btn-primary btn-lg btn-round btn-block text-center">Log in</a> -->
                <button ng-disabled="signInForm.$invalid" ng-click="signIn.validate()" class="btn btn-primary btn-lg btn-round btn-block text-center" type="submit">Log in</button>
              </div>
            </fieldset>
          </form>
          <!-- <section>
            <p class="text-center"><a href="#/pages/forgot-password">Forgot your password?</a></p>
            <p class="text-center text-muted text-small">Don't have an account yet? <a href="">Sign up</a></p>
          </section> -->
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>

