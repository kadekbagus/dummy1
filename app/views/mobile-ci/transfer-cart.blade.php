@extends('mobile-ci.layout')

@section('content')
  <div class="container mobile-ci account-page">
    <div class="row">
      <div class="col-xs-12 text-center">
        <p><b>{{ Lang::get('mobileci.transfer_cart.transfer_message') }}</b></p>
      </div>
    </div>
    <div class="row">
      <div class="col-xs-12 text-center">
        <div id="cartcode" data-cart="{{ $cartdata->cart->cart_code }}"></div>
      </div>
      <div class="col-xs-6 text-center">
        <a id="doneBtn" class="btn btn-success">{{ Lang::get('mobileci.transfer_cart.done_button') }}</a>
      </div>
      <div class="col-xs-6 text-center">
        <a href="{{ url('customer/cart') }}" class="btn btn-info">{{ Lang::get('mobileci.transfer_cart.back_button') }}</a>
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
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
          <h4 class="modal-title" id="doneLabel">{{ Lang::get('mobileci.modals.close_cart_title') }}</h4>
        </div>
        <div class="modal-body">
          <div class="row ">
            <div class="col-xs-12 vertically-spaced">
              <p>{{ Lang::get('mobileci.modals.message_close_cart') }}</p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <div class="row">
            <div class="col-xs-6 pull-right">
              <button type="button" class="btn btn-default" data-dismiss="modal">{{ Lang::get('mobileci.modals.close') }}</button>
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
        $.ajax({
          url: apiPath+'customer/closecart',
          method: 'POST'
        }).done(function(data){
          if(data.message=='moved'){
            location.replace("{{ url('/customer/thankyou') }}");
          } else if(data.message=='notmoved') {
            $('#doneModal').modal();
          }
        });
      })
    });
  </script>
@stop