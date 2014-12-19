@extends('mobile-ci.layout-headless')

@section('content')
<div class="container thankyou">
  <div class="row top-space">
    <div class="col-xs-12 text-center">
      <h2>Transaksi berhasil</h2>
      <h3>Terima kasih atas kunjungan Anda</h3>
      <h5>Jangan lupa cek</h5>
      <a href="{{ $retailer->parent->url }}">{{ $retailer->parent->url }}</a>
      <h5>untuk dapatkan info promo menarik lainya.</h5>
    </div>
    <div class="col-xs-12 text-center vertically-spaced">
      <a href="{{ url('/customer/welcome') }}" class="btn btn-info">Belanja Lagi</a>
    </div>
    <div class="col-xs-12 text-center vertically-spaced">
      Berikut adalah struk Anda. Silahkan disimpan sebagai referensi.
    </div>
    <div class="col-xs-12 text-center vertically-spaced">
      <img class="img-responsive" src="{{ asset('mobile-ci/images/struk.jpg') }}">
    </div>
    <div class="col-xs-12 text-center vertically-spaced">
      <button type="button" class="btn btn-info">Cetak Struk</button>
    </div>
  </div>
</div>
@stop