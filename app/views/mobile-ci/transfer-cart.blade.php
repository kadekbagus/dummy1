@extends('mobile-ci.layout')

@section('content')
  <div class="container mobile-ci account-page">
    <div class="row">
      <div class="col-xs-12 text-center">
        <p><b>Untuk menyelesaikan transfer keranjang, perlihatkan barcode berikut ini kepada kasir.</b></p>
      </div>
    </div>
    <div class="row">
      <div class="col-xs-12 text-center">
        <div id="cartcode" data-cart="{{ $cartdata->cart->cart_code }}"></div>
      </div>
      <div class="col-xs-6 text-center">
        <a href="{{ url('customer/home') }}" class="btn btn-success">Selesai</a>
      </div>
      <div class="col-xs-6 text-center">
        <a href="{{ url('customer/home') }}" class="btn btn-info">Kembali</a>
      </div>
    </div>
  </div>
@stop

@section('modals')
  
@stop

@section('ext_script_bot')
  {{ HTML::script('mobile-ci/scripts/jquery-barcode.min.js') }}
  <script type="text/javascript">
    $(document).ready(function(){
      var cart = $('#cartcode').data('cart');
      console.log(cart);
      var setting = {
        barWidth: 2,
        barHeight: 120,
        moduleSize: 8,
        showHRI: true,
        addQuietZone: true,
        marginHRI: 5,
        bgColor: "#FFFFFF",
        color: "#000000",
        fontSize: 20,
        output: "css",
        posX: 0,
        posY: 0 // type (string)
      }
      $("#cartcode").barcode(
        ""+cart+"", // Value barcode (dependent on the type of barcode)
        "code128",
        setting
      );
    });
  </script>
@stop