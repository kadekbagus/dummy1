@extends('mobile-ci.layout')

@section('content')
  <div class="mobile-ci-container">
    <div class="cart-page cart-info-box">
      <div class="single-box">
        <div class="color-box box-one">
        </div>
        <span class="box-text">{{ Lang::get('mobileci.cart.promo') }}</span>
      </div>
      <div class="single-box">
        <div class="color-box box-two">
        </div>
        <span class="box-text">{{ Lang::get('mobileci.cart.coupon') }}</span>
      </div>
    </div>
    <div class="cart-page the-cart">
      @if(count($cartdata->cartdetails) < 1)
        <div class="row">
          <div class="col-xs-12">
            <p><i>{{ Lang::get('mobileci.cart.no_item') }}</i></p>
          </div>
        </div>
      @else
        <div class="cart-items-list">
          <div class="single-item">
            <div class="single-item-headers">
              <div class="single-header unique-column">
                <span class="header-text">{{ Lang::get('mobileci.cart.item_label') }}</span>
              </div>
              <div class="single-header unique-column">
                <span class="header-text">{{ Lang::get('mobileci.cart.qty_label') }}</span>
              </div>
              <div class="single-header unique-column">
                <span class="header-text">{{ Lang::get('mobileci.cart.price_label') }} ({{ $retailer->parent->currency_symbol }})</span>
              </div>
              <div class="single-header unique-column">
                <span class="header-text">{{ Lang::get('mobileci.cart.total_label') }} ({{ $retailer->parent->currency_symbol }})</span>
              </div>
            </div>
        {{-- product listing --}}
        <?php $x=1;?>
        @foreach($cartdata->cartdetails as $cartdetail)
        
            <div class="single-item-bodies @if($x % 2 == 0) even-line @endif">
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
                <span class="formatted-num">{{ $cartdetail->variant->price }}</span>
              </div>
              <div class="single-body text-right">
                <span class="formatted-num">{{ $cartdetail->original_ammount }}</span>
              </div>
            </div>
            @foreach($cartdetail->promo_for_this_product as $promo)
            <div class="single-item-bodies @if($x % 2 == 0) even-line @endif promo-line">
              <div class="single-body">
                <p><span class="promotion-name" data-promotion="{{ $promo->promotion_id }}"><b>{{ $promo->promotion_name }}</b></span></p>
              </div>
              <div class="single-body">
                <div class="unique-column-properties">
                  
                </div>
              </div>
              <div class="single-body">
                <span class="@if($promo->rule_type == 'product_discount_by_percentage') percentage-num @elseif($promo->rule_type == 'product_discount_by_value') formatted-num @endif">{{ $promo->discount_str }}</span>
              </div>
              <div class="single-body text-right">
                - <span class="formatted-num">{{ $promo->discount }}</span>
              </div>
            </div>
            @endforeach

            @foreach($cartdetail->coupon_for_this_product as $coupon)
            <div class="single-item-bodies @if($x % 2 == 0) even-line @endif coupon-line">
              <div class="single-body">
                <p><span class="product-coupon-name" data-coupon="{{ $coupon->issuedcoupon->promotion_id }}"><b>{{ $coupon->issuedcoupon->promotion_name }}</b></span></p>
              </div>
              <div class="single-body">
                <div class="unique-column-properties">
                  <div class="coupon-remover" data-detail="{{ $coupon->issuedcoupon->issued_coupon_id }}">
                    <span><i class="fa fa-times"></i></span>
                  </div>
                </div>
              </div>
              <div class="single-body">
                <span class="@if($coupon->issuedcoupon->rule_type == 'product_discount_by_percentage') percentage-num @elseif($coupon->issuedcoupon->rule_type == 'product_discount_by_value') formatted-num @endif">{{ $coupon->discount_str }}</span>
              </div>
              <div class="single-body text-right">
                - <span class="formatted-num">{{ $coupon->discount }}</span>
              </div>
            </div>
            @endforeach

            @if(!empty($cartdetail->available_product_coupons))
            <div class="use-coupon single-item-bodies @if($x % 2 == 0) even-line @endif" style="background:#ffac41; color:#623D0C; padding:4px; cursor:pointer;" data-product-variant="{{$cartdetail->product_variant_id}}" data-product="{{$cartdetail->product_id}}">
              <div class="col-xs-12 text-center">
                <span><i class="fa fa-plus-circle"></i> {{ Lang::get('mobileci.cart.use_product_coupon') }} ({{$cartdetail->available_product_coupons}})</span>
              </div>
            </div>
            @endif

        <?php $x++;?>
        @endforeach
            <div class="subtotal">
              <div class="subtotal-title text-right">
                {{ Lang::get('mobileci.cart.subtotal_label') }} : 
              </div>
              <div class="subtotal-price text-right formatted-num">
                @if($retailer->parent->vat_included == 'yes')
                  {{ $cartdata->cartsummary->subtotal_before_cart_promo }}
                @else 
                  {{ $cartdata->cartsummary->subtotal_before_cart_promo_without_tax }}
                @endif
              </div>
            </div>
          </div>
        </div>
    </div>

    @endif

    {{-- cart-based promotions --}}
    @if(count($cartdata->cartsummary->acquired_promo_carts) > 0)
    <div class="cart-page cart-sum">
      <span class="cart-sum-title">{{ Lang::get('mobileci.cart.cart_based_promotion_label') }}</span>
      <div class="cart-sum-headers">
        <div class="cart-promo-single-header">
          <span>{{ Lang::get('mobileci.cart.promotion_label') }}</span>
        </div>
        <div class="cart-promo-single-header">
          <span>{{ Lang::get('mobileci.cart.subtotal_label') }} ({{ $retailer->parent->currency_symbol }})</span>
        </div>
        <div class="cart-promo-single-header">
          <span>{{ Lang::get('mobileci.cart.value_label') }}</span>
        </div>
        <div class="cart-promo-single-header">
          <span>{{ Lang::get('mobileci.cart.discount_label') }} ({{ $retailer->parent->currency_symbol }})</span>
        </div>
      </div>

      @foreach($cartdata->cartsummary->acquired_promo_carts as $promo_cart)
      <div class="cart-sum-bodies">
        <div class="cart-sum-single-body-promo">
          <span class="promotion-name" data-promotion="{{ $promo_cart->promotion_id }}"><b>{{$promo_cart->promotion_name}}</b></span>
        </div>
        <div class="cart-sum-single-body-promo">
          <span class="formatted-num">
            @if($retailer->parent->vat_included == 'yes')
              {{ $cartdata->cartsummary->subtotal_before_cart_promo }}
            @else 
              {{ $cartdata->cartsummary->subtotal_before_cart_promo_without_tax }}
            @endif
          </span>
        </div>
        <div class="cart-sum-single-body-promo">
          <span class="@if($promo_cart->promotionrule->rule_type == 'cart_discount_by_percentage') percentage-num @elseif($promo_cart->promotionrule->rule_type == 'cart_discount_by_value') formatted-num @endif">{{$promo_cart->disc_val_str}}</span>
        </div>
        <div class="cart-sum-single-body-promo text-right">
          <span class="formatted-num">{{$promo_cart->disc_val}}</span>
        </div>
      </div>
      @endforeach
    </div>
    @endif

    {{-- cart-based coupons --}}
    @if(count($cartdata->cartsummary->used_cart_coupons) > 0)
    <div class="cart-page cart-sum">
      <span class="cart-sum-title">{{ Lang::get('mobileci.cart.cart_based_coupon_label') }}</span>
      <div class="cart-sum-headers">
        <div class="cart-coupon-single-header">
          <span>{{ Lang::get('mobileci.cart.coupon_label') }}</span>
        </div>
        <div class="cart-coupon-single-header">
          <span>{{ Lang::get('mobileci.cart.subtotal_label') }} ({{ $retailer->parent->currency_symbol }})</span>
        </div>
        <div class="cart-coupon-single-header">
          <span>{{ Lang::get('mobileci.cart.value_label') }}</span>
        </div>
        <div class="cart-coupon-single-header">
          <span>{{ Lang::get('mobileci.cart.discount_label') }} ({{ $retailer->parent->currency_symbol }})</span>
        </div>
      </div>
      @foreach($cartdata->cartsummary->used_cart_coupons as $coupon_cart)
        @if(!empty($coupon_cart->issuedcoupon))
        <div class="cart-sum-bodies">
          <div class="cart-sum-single-body-promo">
            <span class="coupon-name" data-coupon="{{ $coupon_cart->issuedcoupon->promotion_id }}"><b>{{$coupon_cart->issuedcoupon->promotion_name}}</b></span>
          </div>
          <div class="cart-sum-single-body-promo">
            <span class="formatted-num">
              @if($retailer->parent->vat_included == 'yes')
                {{ $cartdata->cartsummary->subtotal_before_cart_promo }}
              @else 
                {{ $cartdata->cartsummary->subtotal_before_cart_promo_without_tax }}
              @endif
            </span>
          </div>
          <div class="cart-sum-single-body-promo">
            <span class="@if($coupon_cart->issuedcoupon->rule_type == 'cart_discount_by_percentage') percentage-num @elseif($coupon_cart->issuedcoupon->rule_type == 'cart_discount_by_value') formatted-num @endif">{{$coupon_cart->disc_val_str}}</span>
          </div>
          <div class="cart-sum-single-body-promo">
            <span class="formatted-num">{{$coupon_cart->disc_val}}</span>
            <div class="unique-column-properties">
              <div class="coupon-remover" data-detail="{{ $coupon_cart->issuedcoupon->issued_coupon_id }}">
                <span><i class="fa fa-times"></i></span>
              </div>
            </div>
          </div>
        </div>
        @endif  
      @endforeach
    </div>
    @endif

    {{-- cart-based coupon --}}
    @if(count($cartdata->cartsummary->available_coupon_carts) > 0)
    <div class="cart-page cart-sum">
      <span class="cart-sum-title">{{ Lang::get('mobileci.cart.available_cart_based_coupon_label') }}</span>
      <div class="cart-sum-headers">
        <div class="cart-coupon-single-header">
          <span>{{ Lang::get('mobileci.cart.coupon_label') }}</span>
        </div>
        <div class="cart-coupon-single-header">
          <span>{{ Lang::get('mobileci.cart.value_label') }}</span>
        </div>
        <div class="cart-coupon-single-header">
          <span>{{ Lang::get('mobileci.cart.discount_label') }} ({{ $retailer->parent->currency_symbol }})</span>
        </div>
        <div class="cart-coupon-single-header">
          <span>&nbsp;</span>
        </div>
      </div>
      @foreach($cartdata->cartsummary->available_coupon_carts as $available_coupon_cart)
        @foreach($available_coupon_cart->issuedcoupons as $issuedcoupon)
        <div class="cart-sum-bodies">
          <div class="cart-sum-single-body-promo">
            <span class="coupon-name" data-coupon="{{ $available_coupon_cart->promotion_id }}"><b>{{$available_coupon_cart->promotion_name}}</b></span>
          </div>
          <div class="cart-sum-single-body-promo">
            <span class="@if($available_coupon_cart->rule_type == 'cart_discount_by_percentage') percentage-num @elseif($available_coupon_cart->rule_type == 'cart_discount_by_value') formatted-num @endif">{{$available_coupon_cart->disc_val_str}}</span>
          </div>
          <div class="cart-sum-single-body-promo">
            <span class="formatted-num">{{$available_coupon_cart->disc_val}}</span>
          </div>
          <div class="cart-sum-single-body-promo">
            <span><a class="btn btn-info useCouponBtn" data-coupon="{{ $issuedcoupon->issued_coupon_id }}">{{ Lang::get('mobileci.cart.use') }}</a></span>
          </div>
        </div>
        @endforeach
      @endforeach
    </div>
    @endif

    {{-- cart summary --}}
    @if(count($cartdata->cartdetails) > 0)
    <div class="cart-page cart-sum">
      <span class="cart-sum-title">Total</span>
      <div class="cart-sum-headers">
        <div class="cart-sum-single-header">
          <span>{{ Lang::get('mobileci.cart.item_label') }}</span>
        </div>
        <div class="cart-sum-single-header">
          <span>{{ Lang::get('mobileci.cart.subtotal_label') }} ({{ $retailer->parent->currency_symbol }})</span>
        </div>
        <div class="cart-sum-single-header">
          <span>{{ Lang::get('mobileci.cart.taxes_label') }} ({{ $retailer->parent->currency_symbol }})</span>
        </div>
        <div class="cart-sum-single-header">
          <span>{{ Lang::get('mobileci.cart.total_label') }} ({{ $retailer->parent->currency_symbol }})</span>
        </div>
      </div>
      <div class="cart-sum-bodies">
        <div class="cart-sum-single-body">
          <span>{{ $cartdata->cart->total_item + 0 }}</span>
        </div>
        <div class="cart-sum-single-body">
          @if($retailer->parent->vat_included == 'yes')
            <span class="formatted-num">{{ $cartdata->cartsummary->total_to_pay }}</span>
          @else
            <span class="formatted-num">{{ $cartdata->cartsummary->subtotal_wo_tax }}</span>
          @endif
        </div>
        <div class="cart-sum-single-body">
          <span class="formatted-num">{{ $cartdata->cartsummary->vat + 0}}</span>
        </div>
        <div class="cart-sum-single-body">
          <span class="formatted-num"><b>{{ $cartdata->cartsummary->total_to_pay }}</b></span>
        </div>
      </div>
    </div>
    @endif
    
    <div class="cart-page button-group text-center">
      <button id="checkOutBtn" class="btn box-one cart-btn @if(count($cartdata->cartdetails) < 1) disabled @endif" @if(count($cartdata->cartdetails) < 1) disabled @endif>{{ Lang::get('mobileci.cart.checkout_button') }}</button>
      <a href="{{ url('customer/home') }}" class="btn box-three cart-btn">{{ Lang::get('mobileci.cart.continue_button') }}</a>
      <img class="img-responsive img-center img-logo" src="{{ asset($retailer->parent->logo) }}" />
    </div>
  </div>
@stop

@section('modals')
  <!-- Modal -->
  <div class="modal fade" id="checkOutModal" tabindex="-1" role="dialog" aria-labelledby="checkOutLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
      <div class="modal-content">
        <div class="modal-header orbit-modal-header">
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
          <h4 class="modal-title" id="checkOutLabel">{{ Lang::get('mobileci.modals.checkout_title') }}</h4>
        </div>
        <div class="modal-body">
          <div class="row ">
            <div class="col-xs-12 col-sm-6 vertically-spaced">
              <a href="{{ url('customer/transfer') }}" class="btn btn-success btn-block">{{ Lang::get('mobileci.modals.cash_button') }}</a>
            </div>
            <div class="col-xs-12 col-sm-6 vertically-spaced">
              <a href="{{ url('customer/transfer') }}" class="btn btn-success btn-block">{{ Lang::get('mobileci.modals.credit_button') }}</a>
            </div>
          </div>
          <div class="row ">
            <div class="col-xs-12 col-sm-12 vertically-spaced">
              <a href="{{ url('customer/payment') }}"  class="btn btn-success btn-block" >{{ Lang::get('mobileci.modals.online_payment_button') }}</a>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <form name="signUp" id="signUp" method="post" action="{{ url('/customer/signup') }}">
            <button type="button" class="btn btn-danger btn-block" data-dismiss="modal">{{ Lang::get('mobileci.modals.cancel_button') }}</button>
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
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
          <h4 class="modal-title" id="deleteLabel"></h4>
        </div>
        <div class="modal-body">
          <div class="row ">
            <div class="col-xs-12 vertically-spaced">
              <p></p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <form name="deleteCartItem" id="deleteItem">
            <div class="row">
              <input type="hidden" name="detail" id="detail" value="">
              <input type="hidden" name="obj" id="obj" value="">
              <div class="col-xs-6">
                <button type="button" id="cartDeleteBtn" class="btn btn-success btn-block">{{ Lang::get('mobileci.modals.yes_button') }}</button>
              </div>
              <div class="col-xs-6">
                <button type="button" class="btn btn-danger btn-block" data-dismiss="modal">{{ Lang::get('mobileci.modals.cancel_button') }}</button>
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
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
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
                <button type="button" class="btn btn-default btn-block" data-dismiss="modal">{{ Lang::get('mobileci.modals.close') }}</button>
              </div>
            </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal -->
  <div class="modal fade" id="transferFunctionModal" tabindex="-1" role="dialog" aria-labelledby="transferFunctionModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
      <div class="modal-content">
        <div class="modal-header orbit-modal-header">
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
          <h4 class="modal-title" id="transferFunctionModalLabel"><i class="fa fa-lightbulb-o"></i> {{ Lang::get('mobileci.modals.tip_title') }}</h4>
        </div>
        <div class="modal-body">
          <p id="errorModalText">{{ Lang::get('mobileci.modals.message_transfer_cart') }}</p>
          <img src="{{ url('mobile-ci/images/transfer_cart_tip.gif') }}" class="img-responsive">
        </div>
        <div class="modal-footer">
          <div class="pull-left"><input type="checkbox" id="dismiss" name="dismiss" value="0"> {{ Lang::get('mobileci.modals.do_not_show_label') }}</div>
          <div class="pull-right"><button type="button" class="btn btn-default" data-dismiss="modal">{{ Lang::get('mobileci.modals.close') }}</button></div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="hasCouponModal" tabindex="-1" role="dialog" aria-labelledby="hasCouponLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
      <div class="modal-content">
        <div class="modal-header orbit-modal-header">
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
          <h4 class="modal-title" id="hasCouponLabel">{{ Lang::get('mobileci.modals.coupon_title') }}</h4>
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
              <input type="hidden" name="detail" id="detail" value="">
              <div class="col-xs-6">
                <button type="button" id="applyCoupon" class="btn btn-success btn-block">{{ Lang::get('mobileci.modals.coupon_use') }}</button>
              </div>
              <div class="col-xs-6">
                <button type="button" id="denyCoupon" class="btn btn-danger btn-block">{{ Lang::get('mobileci.modals.coupon_ignore') }}</button>
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
{{ HTML::script('mobile-ci/scripts/jquery.cookie.js') }}
<script type="text/javascript">
  // Number.prototype.formatMoney = function(c, d, t){
  // var n = this, 
  //     c = isNaN(c = Math.abs(c)) ? 2 : c, 
  //     d = d == undefined ? "." : d, 
  //     t = t == undefined ? "," : t, 
  //     s = n < 0 ? "-" : "", 
  //     i = parseInt(n = Math.abs(+n || 0).toFixed(c)) + "", 
  //     j = (j = i.length) > 3 ? j % 3 : 0;
  //    return s + (j ? i.substr(0, j) + t : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + t) + (c ? d + Math.abs(n - i).toFixed(c).slice(2) : "");
  // };
  // console.log((123456789.12345).formatMoney(2, '.', ','));

  $(document).ready(function(){
    $('body').off('click', '.use-coupon').on('click', '.use-coupon', function($event){
      $('#hasCouponModal .modal-body p').html('');
      var used_coupons = [];
      var anchor = $(this);
      var prodid = anchor.data('product');
      var prodvarid = anchor.data('product-variant');

      $.ajax({
        url: apiPath+'customer/productcouponpopup',
        method: 'POST',
        data: {
          productid: prodid,
          productvariantid: prodvarid
        }
      }).done(function(data){
        if(data.data.length > 0){
          if(data.status == 'success'){
                for(var i = 0; i < data.data.length; i++){
                  var disc_val;
                  if(data.data[i].rule_type == 'product_discount_by_percentage') disc_val = '-' + (data.data[i].discount_value * 100) + '% off';
                  else if(data.data[i].rule_type == 'product_discount_by_value') disc_val = '- {{ $retailer->parent->currency }} ' + parseFloat(data.data[i].discount_value) +' off';
                  $('#hasCouponModal .modal-body p').html($('#hasCouponModal .modal-body p').html() + '<div class="row vertically-spaced"><div class="col-xs-2"><input type="checkbox" class="used_coupons" name="used_coupons" value="'+ data.data[i].issued_coupon_id +'"></div><div class="col-xs-4"><img style="width:64px;" class="img-responsive" src="{{asset("'+ data.data[i].promo_image +'")}}"></div><div class="col-xs-6">'+data.data[i].promotion_name+'<br>'+ disc_val +'</div></div>');
                }
                $('#hasCouponModal').modal();
              }else{
                  // console.log(data);
              }
          }
      });
      
      $('#hasCouponModal').on('change', '.used_coupons', function($event){
        var coupon = $(this).val();
        if($(this).is(':checked')){
          used_coupons.push(coupon);
        }else{
          used_coupons = $.grep(used_coupons, function(val){
            return val != coupon;
          });
        }
      });

      $('#hasCouponModal').off('click', '#applyCoupon').on('click', '#applyCoupon', function($event){
        $.ajax({
          url: apiPath+'customer/addcouponproducttocart',
          method: 'POST',
          data: {
            productid: prodid,
            productvariantid: prodvarid,
            qty:1,
            coupons : used_coupons
          }
        }).done(function(data){
          // animate cart
          if(data.status == 'success'){
            $('#hasCouponModal').modal('hide');
            if(prodid){
                window.location.reload();
            }
          }
        });
      });

      $('#hasCouponModal').off('click', '#denyCoupon').on('click', '#denyCoupon', function($event){
        $('#hasCouponModal').modal('hide');
      });
    });
    $('.formatted-num').each(function(index){
      var num = parseFloat($(this).text()).toFixed(0);
      var partnum = num.toString().split('.');
      var part1 = partnum[0].replace(/(\d)(?=(\d\d\d)+(?!\d))/g, "$1,");
      $(this).text(part1);
    });
    $('.percentage-num').each(function(index){
      var num = parseFloat($(this).text());
      $(this).text(num+'%');
    });
    // console.log($('.formatted-num').text());
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

    $('.item-remover').click(function(){
      $('#detail').val($(this).data('detail'));
      $('#obj').val('item');
      $('#deleteModal #deleteLabel').text("{{ Lang::get('mobileci.modals.delete_item_title') }}");
      $('#deleteModal .modal-body p').text("{{ Lang::get('mobileci.modals.message_delete_item') }}");
      $('#deleteModal').modal();
    });

    $('.coupon-remover').click(function(){
      $('#detail').val($(this).data('detail'));
      $('#obj').val('coupon');
      $('#deleteModal #deleteLabel').text('{{ Lang::get('mobileci.modals.delete_coupon_title') }}');
      $('#deleteModal .modal-body p').text('{{ Lang::get('mobileci.modals.message_delete_coupon') }}');
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
          $('#previewModal .modal-body p').html('<img class="img-responsive" src="{{asset("'+ data.data.image +'")}}"><br>'+data.data.short_description);
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
          $('#previewModal .modal-body p').html('<img class="img-responsive" src="{{asset("'+ data.data.image +'")}}"><br>'+data.data.description);
          $('#previewModal').modal();
        }else{
          console.log(data);
        }
      });
    });

    $('.coupon-name').click(function(){
      var promotion_detail = $(this).data('coupon');
      $.ajax({
        url: apiPath+'customer/cartcouponpopup',
        method: 'POST',
        data: {
          promotion_detail: promotion_detail
        }
      }).done(function(data){
        if(data.status == 'success'){
          $('#previewModal #previewLabel').text(data.data.promotion_name);
          $('#previewModal .modal-body p').html('<img class="img-responsive" src="{{asset("'+ data.data.image +'")}}"><br>'+data.data.description);
          $('#previewModal').modal();
        }else{
          console.log(data);
        }
      });
    });

    $('.product-coupon-name').click(function(){
      var promotion_detail = $(this).data('coupon');
      $.ajax({
        url: apiPath+'customer/cartproductcouponpopup',
        method: 'POST',
        data: {
          promotion_detail: promotion_detail
        }
      }).done(function(data){
        if(data.status == 'success'){
          $('#previewModal #previewLabel').text(data.data.promotion_name);
          $('#previewModal .modal-body p').html('<img class="img-responsive" src="{{asset("'+ data.data.image +'")}}"><br>'+data.data.description);
          $('#previewModal').modal();
        }else{
          console.log(data);
        }
      });
    });

    $('.useCouponBtn').click(function(){
      var detail = $(this).data('coupon');
      $.ajax({
        url: apiPath+'customer/addcouponcarttocart',
        method: 'POST',
        data: {
          detail: detail
        }
      }).done(function(data){
        if(data.message == 'success'){
          location.reload();
        }else{
          console.log(data);
        }
      });
    });

    $('#cartDeleteBtn').click(function(){
      if($('#obj').val()=='item'){
        var url = apiPath+'customer/deletecart';
      }else if($('#obj').val()=='coupon'){
        var url = apiPath+'customer/deletecouponcart';
      }
      $.ajax({
        url: url,
        method: 'POST',
        data: {
          detail: $('#detail').val()
        }
      }).done(function(data){
        if(data.message == 'success'){
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
        if(!num.val() || num.val() == 0){
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
        if(!num.val() || num.val() == 0){
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