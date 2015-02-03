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
        <div class="cart-items-list">
          <div class="single-item">
            <div class="single-item-headers">
              <div class="single-header unique-column">
                <span class="header-text">Item</span>
              </div>
              <div class="single-header unique-column">
                <span class="header-text">Qty</span>
              </div>
              <div class="single-header unique-column">
                <span class="header-text">Unit Price ({{ $retailer->parent->currency_symbol }})</span>
              </div>
              <div class="single-header unique-column">
                <span class="header-text">Total ({{ $retailer->parent->currency_symbol }})</span>
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
              <div class="single-body">
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
              <div class="single-body">
                - <span class="formatted-num">{{ $promo->discount }}</span>
              </div>
            </div>
            @endforeach

            @foreach($cartdetail->coupon_for_this_product as $coupon)
            <div class="single-item-bodies @if($x % 2 == 0) even-line @endif coupon-line">
              <div class="single-body">
                <p><span class="promotion-name" data-promotion="{{ $coupon->promotion_id }}"><b>{{ $coupon->issuedcoupon->promotion_name }}</b></span></p>
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
              <div class="single-body">
                - <span class="formatted-num">{{ $coupon->discount }}</span>
              </div>
            </div>
            @endforeach
          
        <?php $x++;?>
        @endforeach
          </div>
        </div>
    </div>

    @endif
    {{ $cartdata->cartsummary->vat }}
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
          <!-- <div class="row ">
            <div class="col-xs-12 col-sm-6 vertically-spaced">
              <button ng-click="paymentOptionsCtrl.goTo('creditCard')" type="button" class="btn btn-success btn-block" ng-click="">Credit Card</button>
            </div>
            <div class="col-xs-12 col-sm-6 vertically-spaced">
              <button type="button" class="btn btn-success btn-block" ng-click="">PayPal</button>
            </div>
          </div> -->
          <div class="row ">
            <div class="col-xs-12 col-sm-6 vertically-spaced">
              <a href="{{ url('customer/transfer') }}" class="btn btn-success btn-block">Cash</a>
            </div>
            <div class="col-xs-12 col-sm-6 vertically-spaced">
              <a href="{{ url('customer/transfer') }}" class="btn btn-success btn-block">Credit Card</a>
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

  <!-- Modal -->
  <div class="modal fade" id="transferFunctionModal" tabindex="-1" role="dialog" aria-labelledby="transferFunctionModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
      <div class="modal-content">
        <div class="modal-header orbit-modal-header">
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
          <h4 class="modal-title" id="transferFunctionModalLabel"><i class="fa fa-lightbulb-o"></i> Tip</h4>
        </div>
        <div class="modal-body">
          <p id="errorModalText">Untuk menyelesaikan transfer keranjang, gunakan <i>Transfer Cart</i> pada menu setting dan silahkan tunjukkan smartphone Anda ke kasir.</p>
          <img src="{{ url('mobile-ci/images/transfer_cart_tip.gif') }}" class="img-responsive">
        </div>
        <div class="modal-footer">
          <div class="pull-left"><input type="checkbox" id="dismiss" name="dismiss" value="0"> Jangan tunjukkan pesan ini lagi</div>
          <div class="pull-right"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div>
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
  Number.prototype.formatMoney = function(c, d, t){
  var n = this, 
      c = isNaN(c = Math.abs(c)) ? 2 : c, 
      d = d == undefined ? "." : d, 
      t = t == undefined ? "," : t, 
      s = n < 0 ? "-" : "", 
      i = parseInt(n = Math.abs(+n || 0).toFixed(c)) + "", 
      j = (j = i.length) > 3 ? j % 3 : 0;
     return s + (j ? i.substr(0, j) + t : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + t) + (c ? d + Math.abs(n - i).toFixed(c).slice(2) : "");
  };
  // console.log((123456789.12345).formatMoney(2, '.', ','));

  $(document).ready(function(){
    $('.formatted-num').each(function(index){
      var num = parseFloat($(this).text());
      $(this).text((num).formatMoney(2, '.', ','));
    });
    $('.percentage-num').each(function(index){
      var num = parseFloat($(this).text());
      $(this).text(num+'%');
    });
    console.log($('.formatted-num').text());
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
      $('#deleteModal #deleteLabel').text('Hapus Item');
      $('#deleteModal .modal-body p').text('Apakah Anda yakin akan menghapus item ini dari keranjang belanja?');
      $('#deleteModal').modal();
    });

    $('.coupon-remover').click(function(){
      $('#detail').val($(this).data('detail'));
      $('#obj').val('coupon');
      $('#deleteModal #deleteLabel').text('Hapus Kupon');
      $('#deleteModal .modal-body p').text('Apakah Anda yakin akan menghapus kupon ini dari keranjang belanja? Kupon yang Anda hapus masih dapat digunakan lain kali.');
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
          $('#previewModal .modal-body p').html('<img class="img-responsive" src="'+ data.data.image +'"><br>'+data.data.short_description);
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
          $('#previewModal .modal-body p').html('<img class="img-responsive" src="'+ data.data.image +'"><br>'+data.data.description);
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
          $('#previewModal .modal-body p').html('<img class="img-responsive" src="'+ data.data.image +'"><br>'+data.data.description);
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