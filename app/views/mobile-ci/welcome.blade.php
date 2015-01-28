@extends('mobile-ci.layout-headless')

@section('content')
<div class="container thankyou">
  <div class="row top-space">
    <div class="col-xs-12 text-center vertically-spaced">
      <img class="img-responsive img-center" src="{{ asset($retailer->parent->logo) }}">
    </div>
    <div class="col-xs-12 text-center">
      <h3>Selamat Datang, <br>
        @if(!is_null($user->user_firstname))
        {{ $user->user_firstname }}
        @else
        {{ $user->user_email }}
        @endif
        !
      </h3>
    </div>
    <div class="col-xs-12 text-center vertically-spaced">
      <a href="{{ url('/customer/home') }}" class="btn btn-info">Mulai Belanja</a>
    </div>
    <div class="col-xs-12 text-center vertically-spaced">
      <a href="{{ url('/customer') }}">Bukan 
        @if(!is_null($user->user_firstname))
        {{ $user->user_firstname }}
        @else
        {{ $user->user_email }}
        @endif
        , klik disini.</a>
    </div>
  </div>
</div>
@stop

@section('footer')
  <footer>
    <div class="row">
      <div class="col-xs-12 text-center">
        <img class="img-responsive orbit-footer" src="{{ asset('mobile-ci/images/orbit-footer.png') }}">
      </div>
    </div>
  </footer>
@stop