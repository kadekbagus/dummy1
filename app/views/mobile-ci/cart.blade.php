@extends('mobile-ci.layout')

@section('content')
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
      <div class="cart-items-list">
        <div class="single-item">
          <div class="single-item-headers">
            <div class="single-header">
              <span class="header-text">Item</span>
            </div>
            <div class="single-header unique-column">
              <span class="header-text">Qty</span>
            </div>
            <div class="single-header">
              <span class="header-text">Unit Price</span>
            </div>
            <div class="single-header">
              <span class="header-text">Total</span>
            </div>
          </div>
          <div class="single-item-bodies">
            <div class="single-body">
              <span>Spear of destiny</span>
              <img class="product-image" src="{{ asset('mobile-ci/images/products/kusanagi_sword.jpg') }}" />
            </div>
            <div class="single-body unique-column">
              <div class="unique-column-properties">
                <div class="item-qty">
                  <span>1</span>
                </div>
                <div class="item-remover">
                  <span><i class="fa fa-times"></i></span>
                </div>
              </div>
            </div>
            <div class="single-body">
              <span>125</span>
            </div>
            <div class="single-body">
              <span>125</span>
            </div>
          </div>
        </div>
      </div>
      <div class="cart-items-list">
        <div class="single-item">
          <div class="single-item-headers">
            <div class="single-header">
              <span class="header-text">Item</span>
            </div>
            <div class="single-header unique-column">
              <span class="header-text">Qty</span>
            </div>
            <div class="single-header box-one">
              <span class="header-text">Unit Price</span>
            </div>
            <div class="single-header">
              <span class="header-text">Total</span>
            </div>
          </div>
          <div class="single-item-bodies">
            <div class="single-body">
              <span>Spear of destiny</span>
              <img class="product-image" src="{{ asset('mobile-ci/images/products/kusanagi_sword.jpg') }}" />
            </div>
            <div class="single-body unique-column">
              <div class="unique-column-properties">
                <div class="item-qty">
                  <span>1</span>
                </div>
                <div class="item-remover">
                  <span><i class="fa fa-times"></i></span>
                </div>
              </div>
            </div>
            <div class="single-body">
              <span>125</span>
            </div>
            <div class="single-body">
              <span>125</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="cart-page cart-sum">
      <span class="cart-sum-title">Total</span>
      <div class="cart-sum-headers">
        <div class="cart-sum-single-header">
          <span>Item</span>
        </div>
        <div class="cart-sum-single-header">
          <span>Price</span>
        </div>
        <div class="cart-sum-single-header">
          <span>VAT</span>
        </div>
        <div class="cart-sum-single-header">
          <span>Total</span>
        </div>
      </div>
      <div class="cart-sum-bodies">
        <div class="cart-sum-single-body">
          <span>2</span>
        </div>
        <div class="cart-sum-single-body">
          <span>30</span>
        </div>
        <div class="cart-sum-single-body">
          <span>-</span>
        </div>
        <div class="cart-sum-single-body">
          <span>60</span>
        </div>
      </div>
    </div>
    <div class="cart-page button-group">
      <button class="btn box-one cart-btn" id="checkOutBtn">Check Out</button>
      <button class="btn box-three cart-btn">Continue Shopping</button>
      <img src="{{ asset('mobile-ci/images/logo-default.png') }}" />
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
            <button type="button" class="btn btn-danger btn-block" data-dismiss="modal">Cancel</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@stop

@section('ext_script_bot')
<script type="text/javascript">
  $('#checkOutBtn').click(function(){
    $('#checkOutModal').modal();
  });
</script>
@stop