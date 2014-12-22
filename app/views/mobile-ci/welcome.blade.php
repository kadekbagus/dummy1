@extends('mobile-ci.layout-headless')

@section('content')
<div class="container thankyou">
  <div class="row top-space">
    <div class="col-xs-12 text-center">
      <h3>Selamat Datang, {{ $user->user_firstname }}!</h3>
    </div>
    <div class="col-xs-12 text-center vertically-spaced">
      <img class="img-responsive" src="{{ asset($retailer->parent->logo) }}">
    </div>
    <div class="col-xs-12 text-center vertically-spaced">
      <a href="{{ url('/customer/home') }}" class="btn btn-info">Mulai Belanja</a>
    </div>
    <div class="col-xs-12 text-center vertically-spaced">
      <a href="{{ url('/customer') }}">Bukan {{ $user->user_firstname }}, klik disini.</a>
    </div>
  </div>
</div>
@stop