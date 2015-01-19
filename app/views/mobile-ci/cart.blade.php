@extends('mobile-ci.layout')

@section('content')
  <!-- <pre>{{ var_dump($cartsummary->total_discount) }}</pre> -->
  <div class="mobile-ci-container">
    <div class="cart-page cart-info-box">
      <div class="single-box">
        <div class="color-box box-one">
        </div>
        <span class="box-text">PROMOTION</span>
      </div>
      <div class="single-box">
        <div class="color-box box-two">
        </div>
        <span class="box-text">COUPON</span>
      </div>
    </div>
    <div class="cart-page the-cart">
      @foreach($cartdata->cartdetails as $cartdetail)
      <?php $promos_for_this_item = array_filter($promotions, function($v) use ($cartdetail) { return $v->product_id == $cartdetail->product_id; });?>
      <!-- <pre>{{ var_dump($cartdetail->promo_price) }}</pre> -->
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
              @if(count($promos_for_this_item) > 0)
                {{ 'promotion-column' }}
              @else
                {{ 'unique-column' }}
              @endif
            ">
              <span class="header-text">Unit Price (IDR)</span>
            </div>
            <div class="single-header unique-column">
              <span class="header-text">Total (IDR)</span>
            </div>
          </div>
          <div class="single-item-bodies">
            <div class="single-body">
              <p><span>{{ $cartdetail->product->product_name }}</span></p>
              <p class="attributes">
                @if(!is_null($cartdetail->attributeValue1['value'])) <span>{{$cartdetail->attributeValue1['value']}}</span>@endif
                @if(!is_null($cartdetail->attributeValue2['value'])) <span>{{$cartdetail->attributeValue2['value']}}</span>@endif
                @if(!is_null($cartdetail->attributeValue3['value'])) <span>{{$cartdetail->attributeValue3['value']}}</span>@endif
                @if(!is_null($cartdetail->attributeValue4['value'])) <span>{{$cartdetail->attributeValue4['value']}}</span>@endif
                @if(!is_null($cartdetail->attributeValue5['value'])) <span>{{$cartdetail->attributeValue5['value']}}</span>@endif
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
              <?php $discount = 0; $price = $cartdetail->variant['price']?>
              @if(count($promos_for_this_item) > 0)
                @foreach($promos_for_this_item as $promo)
                    @if($promo->product_id == $cartdetail->product_id) 
                        @if($promo->rule_type == 'product_discount_by_percentage')
                            <?php $discount = $discount +  ($cartdetail->variant['price'] * $promo->discount_value); ?>
                        @elseif ($promo->rule_type == 'product_discount_by_value')
                            <?php $discount = $discount + $promo->discount_value; ?>
                        @endif
                    @endif
                @endforeach
                <?php $price = $price - $discount  + 0; ?>
              @else
                <?php $price = $price + 0; ?>
              @endif
              <span>{{$price}}</span>
            </div>
            <div class="single-body">
              <span>{{ $price * $cartdetail->quantity }}</span>
            </div>
          </div>
        </div>
      </div>
      @endforeach
    </div>
    <div class="cart-page cart-sum">
      <span class="cart-sum-title">Total</span>
      <div class="cart-sum-headers">
        <div class="cart-sum-single-header">
          <span>Item</span>
        </div>
        <div class="cart-sum-single-header">
          <span>Price (IDR)</span>
        </div>
        <div class="cart-sum-single-header">
          <span>VAT (IDR)</span>
        </div>
        <div class="cart-sum-single-header">
          <span>Total (IDR)</span>
        </div>
      </div>
      <div class="cart-sum-bodies">
        <div class="cart-sum-single-body">
          <span>{{ $cartdata->cart->total_item + 0 }}</span>
        </div>
        <div class="cart-sum-single-body">
          <span>{{ $cartsummary->subtotal + 0 }}</span>
        </div>
        <div class="cart-sum-single-body">
          <span>{{ $cartsummary->vat + 0}}</span>
        </div>
        <div class="cart-sum-single-body">
          <span>{{ $cartsummary->total_to_pay + 0 }}</span>
        </div>
      </div>
    </div>
    <div class="cart-page button-group text-center">
      <button class="btn box-one cart-btn" id="checkOutBtn">Check Out</button>
      <button class="btn box-three cart-btn">Continue Shopping</button>
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
          <form name="signUp" id="signUp" method="post" action="{{ url('/customer/signup') }}">
            <div class="row">
              <div class="col-xs-6">
                <button type="button" class="btn btn-success btn-block">Ya</button>
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
      $('#deleteModal').modal();
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
              console(data);
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