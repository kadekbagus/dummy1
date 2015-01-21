@extends('mobile-ci.layout')

@section('content')
  <div class="mobile-ci-container">
    <div class="cart-page cart-info-box">
      <div class="single-box">
        <div class="color-box box-one">
        </div>
        <span class="box-text">PROMO</span>
      </div>
      <div class="single-box">
        <div class="color-box box-two">
        </div>
        <span class="box-text">KUPON</span>
      </div>
    </div>
    <div class="cart-page the-cart">
      @if(count($cartdata->cartdetails) < 1)
        <div class="row">
          <div class="col-xs-12">
            <p><i>Tidak ada item dalam keranjang.</i></p>
          </div>
        </div>
      @else
        @foreach($cartdata->cartdetails as $cartdetail)
        <!-- <pre>{{ print_r($cartdetail) }}</pre> -->
        <div class="cart-items-list">
          <div class="single-item">
            <div class="single-item-headers">
              <div class="single-header unique-column">
                <span class="header-text">Item</span>
              </div>
              <div class="single-header unique-column">
                <span class="header-text">Qty</span>
              </div>
              <div class="single-header 
                @if(count($cartdetail->promoforthisproducts) > 0)
                  {{ 'promotion-column' }}
                @else
                  {{ 'unique-column' }}
                @endif
              ">
                <span class="header-text">Unit Price ({{ $retailer->parent->currency_symbol }})</span>
              </div>
              <div class="single-header unique-column">
                <span class="header-text">Total ({{ $retailer->parent->currency_symbol }})</span>
              </div>
            </div>
            <div class="single-item-bodies">
              <div class="single-body">
                <p><span class="product-name" data-product="{{ $cartdetail->product->product_id }}"><b>{{ $cartdetail->product->product_name }}</b></span></p>
                <p class="attributes">
                  @if(count($cartdetail->attributes) > 0)
                    @foreach($cartdetail->attributes as $attribute)
                      <span>{{$attribute}}</span>
                    @endforeach
                  @endif
                </p>
              </div>
              <div class="single-body">
                <div class="unique-column-properties">
                  <div class="item-qty">
                    <input type="text" readonly="readonly" class="numinput" value="{{ $cartdetail->quantity }}" data-detail="{{ $cartdetail->cart_detail_id }}"  style="background: white; color: black;" >
                  </div>
                  <div class="item-remover" data-detail="{{ $cartdetail->cart_detail_id }}">
                    <span><i class="fa fa-times"></i></span>
                  </div>
                </div>
              </div>
              <div class="single-body">
                <span>{{ $cartdetail->priceafterpromo }}</span>
              </div>
              <div class="single-body">
                <span>{{ $cartdetail->ammountafterpromo }}</span>
              </div>
            </div>
          </div>
        </div>
        @endforeach
      @endif
    </div>

    @if(count($cartsummary->acquired_promo_carts) > 0)
    <div class="cart-page cart-sum">
      <span class="cart-sum-title">Cart Based Promotions</span>
      <div class="cart-sum-headers">
        <div class="cart-promo-single-header">
          <span>Promotion</span>
        </div>
        <div class="cart-promo-single-header">
          <span>Subtotal ({{ $retailer->parent->currency_symbol }})</span>
        </div>
        <div class="cart-promo-single-header">
          <span>Value ({{ $retailer->parent->currency_symbol }})</span>
        </div>
        <div class="cart-promo-single-header">
          <span>Discount ({{ $retailer->parent->currency_symbol }})</span>
        </div>
      </div>
      @foreach($cartsummary->acquired_promo_carts as $promo_cart)
      <div class="cart-sum-bodies">
        <div class="cart-sum-single-body">
          <span class="promotion-name" data-promotion="{{ $promo_cart->promotion_id }}"><b>{{$promo_cart->promotion_name}}</b></span>
        </div>
        <div class="cart-sum-single-body">
          <span>{{$cartsummary->subtotal}}</span>
        </div>
        <div class="cart-sum-single-body">
          <span>{{$promo_cart->disc_val_str}}</span>
        </div>
        <div class="cart-sum-single-body">
          <span>{{$promo_cart->disc_val}}</span>
        </div>
      </div>
      @endforeach
    </div>
    @endif
    <div class="cart-page cart-sum">
      <span class="cart-sum-title">Total</span>
      <div class="cart-sum-headers">
        <div class="cart-sum-single-header">
          <span>Item</span>
        </div>
        <div class="cart-sum-single-header">
          <span>Subtotal ({{ $retailer->parent->currency_symbol }})</span>
        </div>
        <div class="cart-sum-single-header">
          <span>VAT ({{ $retailer->parent->currency_symbol }})</span>
        </div>
        <div class="cart-sum-single-header">
          <span>Total ({{ $retailer->parent->currency_symbol }})</span>
        </div>
      </div>
      <div class="cart-sum-bodies">
        <div class="cart-sum-single-body">
          <span>{{ $cartdata->cart->total_item + 0 }}</span>
        </div>
        <div class="cart-sum-single-body">
          <span>{{ $cartsummary->subtotalaftercartpromo + 0 }}</span>
        </div>
        <div class="cart-sum-single-body">
          <span>{{ $cartsummary->vat + 0}}</span>
        </div>
        <div class="cart-sum-single-body">
          <span><b>{{ $cartsummary->total_to_pay + 0 }}</b></span>
        </div>
      </div>
    </div>
    <div class="cart-page button-group text-center">
      <button id="checkOutBtn" class="btn box-one cart-btn @if(count($cartdata->cartdetails) < 1) disabled @endif" @if(count($cartdata->cartdetails) < 1) disabled @endif>Check Out</button>
      <a href="{{ url('customer/home') }}" class="btn box-three cart-btn">Continue Shopping</a>
      <img class="img-responsive img-center" src="{{ asset($retailer->parent->logo) }}" />
    </div>
  </div>
@stop

@section('modals')
  <!-- Modal -->
  <div class="modal fade" id="checkOutModal" tabindex="-1" role="dialog" aria-labelledby="checkOutLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
      <div class="modal-content">
        <div class="modal-header orbit-modal-header">
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
          <h4 class="modal-title" id="checkOutLabel">Checkout</h4>
        </div>
        <div class="modal-body">
          <div class="row ">
            <div class="col-xs-12 col-sm-6 vertically-spaced">
              <button ng-click="paymentOptionsCtrl.goTo('creditCard')" type="button" class="btn btn-success btn-block" ng-click="">Credit Card</button>
            </div>
            <div class="col-xs-12 col-sm-6 vertically-spaced">
              <button type="button" class="btn btn-success btn-block" ng-click="">PayPal</button>
            </div>
          </div>
          <div class="row ">
            <div class="col-xs-12 col-sm-6 vertically-spaced">
              <button ng-click="paymentOptionsCtrl.goTo('transferCart')" type="button" class="btn btn-success btn-block" ng-click="">Cash</button>
            </div>
            <div class="col-xs-12 col-sm-6 vertically-spaced">
              <button ng-click="paymentOptionsCtrl.goTo('transferCart')" type="button" class="btn btn-success btn-block" ng-click="">Card Present</button>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <form name="signUp" id="signUp" method="post" action="{{ url('/customer/signup') }}">
            <button type="button" class="btn btn-danger btn-block" data-dismiss="modal">Batal</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <!-- Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
      <div class="modal-content">
        <div class="modal-header orbit-modal-header">
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
          <h4 class="modal-title" id="deleteLabel">Hapus Item</h4>
        </div>
        <div class="modal-body">
          <div class="row ">
            <div class="col-xs-12 vertically-spaced">
              <p>Apakah Anda yakin akan menghapus item ini dari keranjang belanja?</p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <form name="deleteCartItem" id="deleteItem">
            <div class="row">
              <input type="hidden" name="detail" id="detail" value="">
              <div class="col-xs-6">
                <button type="button" id="cartDeleteBtn" class="btn btn-success btn-block">Ya</button>
              </div>
              <div class="col-xs-6">
                <button type="button" class="btn btn-danger btn-block" data-dismiss="modal">Batal</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal -->
  <div class="modal fade" id="previewModal" tabindex="-1" role="dialog" aria-labelledby="previewLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
      <div class="modal-content">
        <div class="modal-header orbit-modal-header">
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
          <h4 class="modal-title" id="previewLabel"></h4>
        </div>
        <div class="modal-body">
          <div class="row ">
            <div class="col-xs-12 vertically-spaced">
              <p></p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
            <div class="row">
              <div class="col-xs-6 pull-right">
                <button type="button" class="btn btn-default btn-block" data-dismiss="modal">Tutup</button>
              </div>
            </div>
        </div>
      </div>
    </div>
  </div>

  <table class="ui-bar-a" id="n_keypad" style="display: none; -khtml-user-select: none;">
    <tr>
       <td class="num numero"><a data-role="button" data-theme="b" >7</a></td>
       <td class="num numero"><a data-role="button" data-theme="b" >8</a></td>
       <td class="num numero"><a data-role="button" data-theme="b" >9</a></td>
       <td class="del"><a data-role="button" data-theme="e" ><i class="fa fa-long-arrow-left"></i></a></td>
    </tr>
    <tr>
       <td class="num numero"><a data-role="button" data-theme="b" >4</a></td>
       <td class="num numero"><a data-role="button" data-theme="b" >5</a></td>
       <td class="num numero"><a data-role="button" data-theme="b" >6</a></td>
       <td class="clear"><a data-role="button" data-theme="e" ><i class="fa fa-close"></i></a></td>
    </tr>
    <tr>
       <td class="num numero"><a data-role="button" data-theme="b" >1</a></td>
       <td class="num numero"><a data-role="button" data-theme="b" >2</a></td>
       <td class="num numero"><a data-role="button" data-theme="b" class="">3</a></td>
       <td class="emptys"><a data-role="button" data-theme="e">&nbsp;</a></td>
    </tr>
    <tr>
       <td class="neg"><a data-role="button" data-theme="e">-</a></td>
       <td class="num zero"><a data-role="button" data-theme="b" class="zero">0</a></td>
       <td class="pos"><a data-role="button" data-theme="e">+</a></td>
       <td class="done"><a data-role="button" data-theme="e" ><i class="fa fa-check"></i></a></td>
    </tr>
  </table>
@stop

@section('ext_script_bot')
<script type="text/javascript">
  $(document).ready(function(){
    $('.item-remover').click(function(){
      $('#detail').val($(this).data('detail'));
      $('#deleteModal').modal();
    });

    $('.product-name').click(function(){
      var detail = $(this).data('product');
      $.ajax({
        url: apiPath+'customer/cartproductpopup',
        method: 'POST',
        data: {
          detail: detail
        }
      }).done(function(data){
        if(data.status == 'success'){
          $('#previewModal #previewLabel').text(data.data.product_name);
          $('#previewModal .modal-body p').html('<img class="img-responsive" src="'+ data.data.image +'">');
          $('#previewModal').modal();
        }else{
          console.log(data);
        }
      });
    });

    $('.promotion-name').click(function(){
      var promotion_detail = $(this).data('promotion');
      $.ajax({
        url: apiPath+'customer/cartpromopopup',
        method: 'POST',
        data: {
          promotion_detail: promotion_detail
        }
      }).done(function(data){
        if(data.status == 'success'){
          $('#previewModal #previewLabel').text(data.data.promotion_name);
          $('#previewModal .modal-body p').html('<img class="img-responsive" src="'+ data.data.image +'"><br>'+data.data.description);
          $('#previewModal').modal();
        }else{
          console.log(data);
        }
      });
    });

    $('#cartDeleteBtn').click(function(){
      $.ajax({
        url: apiPath+'customer/deletecart',
        method: 'POST',
        data: {
          detail: $('#detail').val()
        }
      }).done(function(data){
        if(data.status == 'success'){
          location.reload();
        }else{
          console.log(data);
        }
      });
    });

    $('#checkOutBtn').click(function(){
      $('#checkOutModal').modal();
    });
    var num;
    var lastnum;
    var detail;
    var is_open = false;
    $('.numinput').click(function(){
        var tops = $(this).offset().top;
        var lefts = $(this).offset().left;
        num = $(this);
        $('#n_keypad').fadeToggle('fast', function(){
          if(is_open) {
            is_open = false;
          }else{
            is_open = true;
            num.val('');
          }
        }).offset({
          top: tops + 24,
          left: lefts
        });
        if(!num.val()){
          num.val(lastnum);
        }else{
          lastnum = num.val();
          detail = num.data('detail');
        }
    });
    $('.done').click(function(){
        if(is_open) {
          is_open = false;
        }else{
          is_open = true;
          num.val('');
        }
        $('#n_keypad').hide();
        if(!num.val()){
          num.val(lastnum);
        }else{
          $.ajax({
            url: apiPath+'customer/updatecart',
            method: 'POST',
            data: {
              detail: detail,
              qty:num.val()
            }
          }).done(function(data){
            if(data.status == 'success'){
              location.reload();
            }else{
              console.log(data);
            }
          });
        }
    });
    $('.numero').click(function(){
      if (!isNaN(num.val())) {
         if (parseInt($('.numinput').val()) == 0) {
           num.val($(this).children('a').text());
         } else {
           num.val(num.val() + $(this).children('a').text());
         }
      }
    });
    $('.neg').click(function(){
        if (!isNaN(num.val()) && num.val().length > 0) {
          if (parseInt(num.val()) > 0) {
            num.val(parseInt(num.val()) - 1);
          }
        }
    });
    $('.pos').click(function(){
        if (!isNaN(num.val()) && num.val().length > 0) {
          num.val(parseInt(num.val()) + 1);
        }
    });
    $('.del').click(function(){
        num.val(num.val().substring(0,num.val().length - 1));
    });
    $('.clear').click(function(){
        num.val('');
    });
    $('.zero').click(function(){
      if (!isNaN(num.val())) {
        if (parseInt(num.val()) != 0) {
          num.val(num.val() + $(this).children('a').text());
        }
      }
    });
  });
</script>
@stop