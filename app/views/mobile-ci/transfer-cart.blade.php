@extends('mobile-ci.layout')

@section('content')
  <div class="container mobile-ci account-page">
    <div class="row">
      <div class="col-xs-12 text-center">
        <p><b>Perlihatkan barcode berikut ini kepada kasir</b></p>
      </div>
    </div>
    <div class="row">
      <div class="col-xs-12 text-center">
        <p><i class="fa fa-barcode" style="font-size: 9em;"></i></p>
      </div>
      <div class="col-xs-12 text-center">
        <a href="{{ url('customer/home') }}" class="btn btn-success">Done</a>
      </div>
    </div>
  </div>
@stop

@section('modals')
  <!-- Modal -->
  <div class="modal fade" id="transferFunctionModal" tabindex="-1" role="dialog" aria-labelledby="transferFunctionModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
      <div class="modal-content">
        <div class="modal-header orbit-modal-header">
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
          <h4 class="modal-title" id="transferFunctionModalLabel">{{ Lang::get('mobileci.page_title.transfercart') }}</h4>
        </div>
        <div class="modal-body">
          <p id="errorModalText">Lakukan "Transfer Cart" untuk memindahkan item belanja Anda ke sistem kasir, agar Anda dapat melakukan transaksi di kasir</p>
        </div>
        <div class="modal-footer">
          <div class="pull-left"><input type="checkbox" id="dismiss" name="dismiss" value="0"> Jangan tunjukkan pesan ini lagi</div>
          <div class="pull-right"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div>
        </div>
      </div>
    </div>
  </div>
@stop

@section('ext_script_bot')
  {{ HTML::script('mobile-ci/scripts/jquery.cookie.js') }}
  <script type="text/javascript">
    $(document).ready(function(){
      console.log($.cookie('dismiss_transfercart_popup'));
      $('#dismiss').change(function(){
        if($(this).is(':checked')) {
          $.cookie('dismiss_transfercart_popup', 't', { expires: 30 });
        } else {
          $.cookie('dismiss_transfercart_popup', 'f', { expires: 30 });
        }
      });
      if(typeof $.cookie('dismiss_transfercart_popup') === 'undefined') {
        $.cookie('dismiss_transfercart_popup', 'f', { expires: 30 });
        $('#transferFunctionModal').modal();
      }
      else{
        if($.cookie('dismiss_transfercart_popup') == 'f') {
          $('#transferFunctionModal').modal();
        }
      }
    });
  </script>
@stop