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
        <p><i class="fa fa-barcode" style="font-size: 9em;"></i></p>
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
  {{ HTML::script('mobile-ci/scripts/jquery.cookie.js') }}
  <script type="text/javascript">
   
  </script>
@stop