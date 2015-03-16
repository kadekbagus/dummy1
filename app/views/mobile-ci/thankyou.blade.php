@extends('mobile-ci.layout-headless')

@section('ext_style')
  
  <style type="text/css">
  body{
    font-family: 'Arial';
  }
  </style>
@stop

@section('content')
<div class="container thankyou">
  <div class="row top-space">
    <div class="col-xs-12 text-center">
      <h2>{{ Lang::get('mobileci.thank_you.thank_you') }}</h2>
      <div id="receipt" class="receipt">
            <div class="row vertically-spaced">
              <div class="col-xs-12 text-center vertically-spaced">
                <img class="img-responsive" src="{{ asset($retailer->parent->logo) }}" style="margin:0 auto;"/>
              </div>
              <div class="col-xs-12 text-center vertically-spaced">
                {{ $retailer->parent->address_line1 }}
              </div>
              <div class="col-xs-12 text-center vertically-spaced">
                @if(! empty($retailer->parent->ticket_header))
                  {{ nl2br($retailer->parent->ticket_header) }}
                @endif
              </div>
              <div class="col-xs-12 text-left vertically-spaced">
                {{ Lang::get('mobileci.cart.transaction_id_label') }}: {{ str_pad($transaction->transaction_id, 10, '0', STR_PAD_LEFT) }}
                <br>
                {{ Lang::get('mobileci.cart.date_label') }}: {{ date('r', strtotime($transaction->created_at)) }}
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
                <h4 class="text-center"><strong>{{ Lang::get('mobileci.cart.items_label') }}</strong></h4>
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
                            {{ $cartdetail->quantity }}
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
                        <span>-</span> <span class="formatted-num">{{ $promo->discount }}</span>
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
                          
                        </div>
                      </div>
                      <div class="single-body">
                        <span class="@if($coupon->issuedcoupon->rule_type == 'product_discount_by_percentage') percentage-num @elseif($coupon->issuedcoupon->rule_type == 'product_discount_by_value') formatted-num @endif">{{ $coupon->discount_str }}</span>
                      </div>
                      <div class="single-body text-right">
                        <span>-</span> <span class="formatted-num">{{ $coupon->discount }}</span>
                      </div>
                    </div>
                    @endforeach

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
            @endif
            </div>

            {{-- cart-based promotions --}}
            @if(count($cartdata->cartsummary->acquired_promo_carts) > 0)
            <div class="cart-page cart-sum">
              <h4 class="cart-sum-title">{{ Lang::get('mobileci.cart.cart_based_promotion_label') }}</h4>
              <div class="cart-sum-headers cart-sum-headers-promotion">
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
              <h4 class="cart-sum-title">{{ Lang::get('mobileci.cart.cart_based_coupon_label') }}</h4>
              <div class="cart-sum-headers cart-sum-headers-coupon">
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
                  </div>
                </div>
                @endif  
              @endforeach
            </div>
            @endif

            {{-- cart summary --}}
            @if(count($cartdata->cartdetails) > 0)
            <div class="receipt-summary text-left">
              <h4 class="receipt-summary title-text text-center">{{ Lang::get('mobileci.cart.total_label') }}</h4>
              <div class="cart-sum-headers">
                <div class="cart-sum-single-header">
                  <span>{{ Lang::get('mobileci.cart.item_label') }}</span>
                  <span class="right-total-value pull-right">{{ $cartdata->cart->total_item + 0 }}</span>
                </div>
                <div class="cart-sum-single-header">
                  <span>{{ Lang::get('mobileci.cart.subtotal_label') }} ({{ $retailer->parent->currency_symbol }})</span>
                  @if($retailer->parent->vat_included == 'yes')
                    <span class="formatted-num right-total-value pull-right">{{ $cartdata->cartsummary->total_to_pay }}</span>
                  @else
                    <span class="formatted-num right-total-value pull-right">{{ $cartdata->cartsummary->subtotal_wo_tax }}</span>
                  @endif
                </div>
                <div class="cart-sum-single-header">
                  
                  <!-- <span>Taxes ({{ $retailer->parent->currency_symbol }})</span>
                  <span class="formatted-num right-total-value pull-right">{{ $cartdata->cartsummary->vat + 0}}</span> -->

                  @foreach($cartdata->cartsummary->taxes as $tax)
                    @if(!empty($tax->total_tax))
                    <div>
                      <span>{{ $tax->tax_name }} ({{ ($tax->tax_value * 100).'%' }})</span>
                      <span class="formatted-num right-total-value pull-right">{{ $tax->total_tax }}</span>
                    </div>
                    @endif
                  @endforeach
                  
                </div>
                <div class="cart-sum-single-header">
                  <span>{{ Lang::get('mobileci.cart.total_label') }} ({{ $retailer->parent->currency_symbol }})</span>
                  <span class="formatted-num right-total-value pull-right"><b>{{ $cartdata->cartsummary->total_to_pay }}</b></span>
                </div>
              </div>
            </div>
            @endif

             {{-- acquired coupons --}}
            @if(count($acquired_coupons) > 0)
              <div class="receipt-summary text-left">
                <h4 class="receipt-summary title-text text-center">{{ Lang::get('mobileci.cart.acquired_coupons_label') }}</h4>
                @foreach($acquired_coupons as $acquired_coupon)
                <div class="cart-acq-coupon-headers">
                  <span>{{ $acquired_coupon->coupon->promotion_name }}</span><span class="pull-right">(1x)</span>
                </div>
                <div class="cart-acq-coupon-content">
                  {{ $acquired_coupon->coupon->description }}
                </div>
                <div class="cart-acq-coupon-rules">
                  <table class="table table-striped">
                    <tr>
                      <td>{{ Lang::get('mobileci.coupon_detail.coupon_code_label') }}</td>
                      <td>{{ $acquired_coupon->issued_coupon_code }}</td>
                    </tr>
                    <tr>
                      <td>{{ Lang::get('mobileci.coupon_detail.validity_label') }}</td>
                      @if($acquired_coupon->coupon->is_permanent == 'N')
                          @if(strtotime($acquired_coupon->expired_date) < strtotime($acquired_coupon->coupon->end_date))
                          <td>{{ date('j M Y', strtotime($acquired_coupon->expired_date)) }}</td>
                          @else
                          <td>{{ date('j M Y', strtotime($acquired_coupon->coupon->end_date)) }}</td>
                          @endif
                      @else
                          <td>{{ date('j M Y', strtotime($acquired_coupon->expired_date)) }}</td>
                      @endif
                    </tr>
                    <?php $c = 0;?>
                    @foreach($acquired_coupon->coupon->redeemretailers as $redeem_retailer)
                      @if($c==0)
                      <tr>
                      <td>{{ Lang::get('mobileci.thank_you.retailer') }}</td>
                      @else
                      <td>&nbsp;</td>
                      @endif
                      <td>{{ $redeem_retailer->name }}</td>
                      <?php $c++;?>
                      </tr>
                    @endforeach
                    <tr>
                        @if($acquired_coupon->coupon->couponrule->discount_object_type == 'product')
                          <td>{{ Lang::get('mobileci.coupon_list.product_label') }}</td>
                          <td>
                            {{ $acquired_coupon->coupon->couponrule->discountproduct->product_name }}
                          </td>
                        @elseif($acquired_coupon->coupon->couponrule->discount_object_type == 'family')
                          <td>{{ Lang::get('mobileci.coupon_list.category_label') }}</td>
                          <td class="coupon-category">
                            @if(!is_null($acquired_coupon->coupon->couponrule->discountcategory1))
                            <span>{{ $acquired_coupon->coupon->couponrule->discountcategory1->category_name }}</span>
                            @endif
                            @if(!is_null($acquired_coupon->coupon->couponrule->discountcategory2))
                            <span>{{ $acquired_coupon->coupon->couponrule->discountcategory2->category_name }}</span>
                            @endif
                            @if(!is_null($acquired_coupon->coupon->couponrule->discountcategory3))
                            <span>{{ $acquired_coupon->coupon->couponrule->discountcategory3->category_name }}</span>
                            @endif
                            @if(!is_null($acquired_coupon->coupon->couponrule->discountcategory4))
                            <span>{{ $acquired_coupon->coupon->couponrule->discountcategory4->category_name }}</span>
                            @endif
                            @if(!is_null($acquired_coupon->coupon->couponrule->discountcategory5))
                            <span>{{ $acquired_coupon->coupon->couponrule->discountcategory5->category_name }}</span>
                            @endif
                          </td>
                        @else
                          <td>Cart</td>
                          <td>&nbsp</td>
                        @endif
                      </td>
                    </tr>
                  </table>
                </div>
              @endforeach
              </div>
            @endif
            <div class="row vertically-spaced">
              <div class="col-xs-12 text-center vertically-spaced">
                @if(! empty($retailer->parent->ticket_footer))
                  {{ nl2br($retailer->parent->ticket_footer) }}
                @endif
              </div>
            </div>
      </div>
      <a class="btn btn-info" id="saveTicketBtn" data-transaction="{{ $transaction->transaction_id }}" download="receipt_{{\Carbon\Carbon::now()}}.png">{{ Lang::get('mobileci.thank_you.save_ticket_button') }}</a>
      <h3>{{ Lang::get('mobileci.thank_you.thank_you_message') }}</h3>
      @if(!empty($retailer->parent->url))
      <h5>{{ Lang::get('mobileci.thank_you.dont_forget_message') }}</h5>
      <a href="{{ $retailer->parent->url }}">{{ $retailer->parent->url }}</a>
      <h5>{{ Lang::get('mobileci.thank_you.promo_message') }}</h5>
      @endif
    </div>
    <div class="col-xs-12 text-center vertically-spaced">
      <a href="{{ url('/customer/logout') }}" class="btn btn-info">{{ Lang::get('mobileci.thank_you.shop_again_button') }}</a>
    </div>
  </div>
</div>
@stop

@section('ext_script_bot')
  {{ HTML::script('mobile-ci/scripts/html2canvas.min.js') }}
  <script type="text/javascript">
    var cnv;
    $(document).ready(function(){
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
      
      
      
      var button = document.getElementById('saveTicketBtn');
      button.addEventListener('click', function (e) {
          var canvas = document.getElementById('receipt-img');
          var dataURL = canvas.toDataURL('image/png');
          button.href = dataURL;

          var transactiondata = $(this).data('transaction');

          $.ajax({
            url: '{{ route('click-save-receipt-activity') }}',
            data: {
              transactiondata: transactiondata
            },
            method: 'POST'
          });

      });
    });

    $(window).bind("load", function() {
      $(".receipt").css('font-family', '"Courier New", Courier, monospace');
      html2canvas($(".receipt"), {
          onrendered: function(canvas) {
              theCanvas = canvas;
              canvas.id = 'receipt-img';
              // document.getElementById('receipt').appendChild(canvas);
              $('#receipt').after(canvas);

              $.ajax({
                url: '{{ route('send-ticket') }}',
                data: {
                  ticketdata: canvas.toDataURL('image/png'),
                  transactionid: '{{ $transaction->transaction_id }}'
                },
                method: 'POST'
              });
              // Convert and download as image 
              // Canvas2Image.saveAsPNG(canvas); 
              // $("#img-out").append(canvas);
              // cnv = canvas;
              // Clean up 
              //document.body.removeChild(canvas);
          }
      });
      
      $('.receipt').hide();
    });
  </script>
@stop