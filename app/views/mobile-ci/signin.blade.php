@extends('mobile-ci.layout-headless')

@section('content')
  <div class="row top-space">
    <div class="col-xs-12">
      <header>
        <div class="row vertically-spaced">
          <div class="col-xs-12 text-center">
            <span>Welcome!</span>
          </div>
        </div>
        <div class="row vertically-spaced">
          <div class="col-xs-12 text-center">
            <img src="{{ asset('mobile-ci/images/logo-default.png') }}" />
          </div>
        </div>
      </header>
      <form name="loginForm">
        <div class="form-group">
          <input type="text" class="form-control" name="userEmail" />
        </div>
        <div class="form-group">
          <button type="submit" class="btn btn-info btn-block">Login</button>
        </div>
      </form>
    </div>
  </div>
@stop

@section('ext_script_bot')
  <script type="text/javascript">
    $(document).ready(function(){

    });
  </script>
@stop