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
        <a id="doneBtn" class="btn btn-success">Selesai</a>
      </div>
      <div class="col-xs-6 text-center">
        <a href="{{ url('customer/home') }}" class="btn btn-info">Kembali</a>
      </div>
    </div>
  </div>
@stop

@section('modals')
  <!-- Modal -->
  <div class="modal fade" id="doneModal" tabindex="-1" role="dialog" aria-labelledby="doneLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
      <div class="modal-content">
        <div class="modal-header orbit-modal-header">
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
          <h4 class="modal-title" id="doneLabel">Tutup Keranjang</h4>
        </div>
        <div class="modal-body">
          <div class="row ">
            <div class="col-xs-12 vertically-spaced">
              <p>Pastikan keranjang belanja Anda sudah tertransfer ke kasir.<br>Semua item dalam keranjang Anda akan hilang apabila Anda menekan tombol 'Ya'.<br>Kupon yang tidak jadi terpakai akan diaktifkan kembali.</p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <div class="row">
            <div class="col-xs-6">
              <button type="button" id="sureBtn" class="btn btn-success btn-block">Ya</button>
            </div>
            <div class="col-xs-6">
              <button type="button" class="btn btn-danger btn-block" data-dismiss="modal">Batal</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@stop

@section('ext_script_bot')
  {{ HTML::script('mobile-ci/scripts/jquery-barcode.min.js') }}
  <script type="text/javascript">
    $(document).ready(function(){
      var cart = $('#cartcode').data('cart');
      // console.log(cart);
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
      $('#doneBtn').click(function(){
        $('#doneModal').modal();
      });
      $('#sureBtn').click(function(){
        
      })
    });
  </script>
@stop