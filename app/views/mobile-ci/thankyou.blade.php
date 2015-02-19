@extends('mobile-ci.layout-headless')

@section('ext_style')
  
@stop

@section('content')
<div class="container thankyou">
  <div class="row top-space">
    <div class="col-xs-12 text-center">
      <h2>Transaksi berhasil</h2>
      <div class="receipt">
        <div class="row">
          <div class="col-xs-12">
            
          </div>
        </div>
      </div>
      <h3>Terima kasih atas kunjungan Anda</h3>
      <h5>Jangan lupa kunjungi</h5>
      <a href="{{ $retailer->parent->url }}">{{ $retailer->parent->url }}</a>
      <h5>untuk dapatkan info promo menarik lainya.</h5>
    </div>
    <div class="col-xs-12 text-center vertically-spaced">
      <a href="{{ url('/customer/logout') }}" class="btn btn-info">Belanja Lagi</a>
    </div>
  </div>
</div>
@stop