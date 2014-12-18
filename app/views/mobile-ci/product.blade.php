@extends('mobile-ci.layout')

@section('ext_style')
	{{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
<!-- product -->
<div class="row product">
	<div class="col-xs-12 product-img">
		<!-- <div ng-include="product.ribbon"></div> -->
		<div>
			<div class="ribbon-wrapper-yellow ribbon1st">
				<div class="ribbon-yellow">Promo</div>
			</div>
			<div class="ribbon-wrapper-red ribbon2nd">
				<div class="ribbon-red">New</div>
			</div>
		</div>
		<div class="zoom-wrapper">
			<div class="zoom"><a href="{{ asset('mobile-ci/images/products/product1.png') }}" data-featherlight="image"><img alt="" src="{{ asset('mobile-ci/images/product-zoom.png') }}" ></a></div>
		</div>
		<a href="{{ asset('mobile-ci/images/products/product1.png') }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset('mobile-ci/images/products/product1.png') }}"></a>
	</div>
	<div class="col-xs-12 main-theme product-detail">
		<div class="row">
			<div class="col-xs-12">
				<h3>Awesome Polo Shirt</h3>
			</div>
			<div class="col-xs-12">
				<p>A furred, magic resistant shirt that is feared by wizards.</p>
			</div>
		</div>
		<div class="additional-detail">
			<div class="row">
				<div class="col-xs-12">
					<h3>Discount Coupon Promo</h3>
				</div>
				<div class="col-xs-3 col-xs-height">
					<h4>30%</h4>
				</div>
				<div class="col-xs-9 col-xs-height">
					<p>Happy Halloween</p>
				</div>
			</div>
			<div class="row additional-dates">
				<div class="col-xs-5 col-xs-height">
					<p>19 Sep 2014</p>
				</div>
				<div class="col-xs-2 col-xs-height">
					<p>to</p>
				</div>
				<div class="col-xs-5 col-xs-height">
					<p>31 Oct 2014</p>
				</div>
			</div>
		</div>
	</div>
	<div class="col-xs-12 product-attributes">
		<div class="row">
			<div class="col-xs-4 main-theme-text">
				<div class="radio-container">
					<ul><h5>Size</h5>
				        <li><input type="radio" name="size" value="S" ><span class="attribute-title">S</span></li>
				        <li><input type="radio" name="size" value="M" ><span class="attribute-title">M</span></li>
				        <li><input type="radio" name="size" value="L" ><span class="attribute-title">L</span></li>
				        <li><input type="radio" name="size" value="XL" ><span class="attribute-title">XL</span></li>
				    </ul>
				</div>
			</div>
			<div class="col-xs-4 main-theme-text">
				<div class="radio-container">
					<ul><h5>Colors</h5>
				        <li><input type="radio" name="color" value="Red" ><span class="attribute-title">Red</span></li>
				        <li><input type="radio" name="color" value="Magenta" ><span class="attribute-title">Magenta</span></li>
				        <li><input type="radio" name="color" value="Blue" ><span class="attribute-title">Blue</span></li>
				        <li><input type="radio" name="color" value="Green" ><span class="attribute-title">Green</span></li>
				        <li><input type="radio" name="color" value="Grey" ><span class="attribute-title">Gray</span></li>
				    </ul>
				</div>
			</div>
			<div class="col-xs-4 main-theme-text">
				<div class="radio-container">
					<ul><h5>Sleeve</h5>
				        <li><input type="radio" name="sleeve" value="Long Sleeve" ><span class="attribute-title">Long Sleeve</span></li>
				        <li><input type="radio" name="sleeve" value="Short Sleeve" ><span class="attribute-title">Short Sleeve</span></li>
				    </ul>
				</div>
			</div>
		</div>
	</div>
	<div class="col-xs-12 product-bottom main-theme ">
		<div class="row">
			<div class="col-xs-6">
				<h4>Code : ID000001</h4>
			</div>
		</div>
		<div class="row">
			<div class="col-xs-6 strike">
				<h3>USD 60</h3>
			</div>
			<div class="col-xs-6">
				<h3>USD 50</h3>
			</div>	
		</div>
		<div class="row text-center product-control">
			<!-- <div class="col-xs-2 col-xs-offset-8 col-ms-1 col-ms-offset-10 col-md-1 col-md-offset-10 col-sm-1 col-sm-offset-10 col-lg-1 col-lg-offset-10"> -->
			<div class="col-xs-2  col-ms-1  col-md-1  col-sm-1  col-lg-1">
				<div class="circlet btn-blue" id="backBtnProduct">
					<img src="{{ asset('mobile-ci/images/back-clear.png') }}" >
				</div>
			</div>
			<div class="col-xs-2 col-ms-1 col-md-1 col-sm-1 col-lg-1 pull-right">
				<div class="circlet btn-blue pull-right">
					<img src="{{ asset('mobile-ci/images/cart-clear.png') }}" >
				</div>
			</div>
		</div>
	</div>
</div>
<!-- end of product -->
@stop

@section('ext_script_bot')
	{{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
	<script type="text/javascript">
		$('#backBtnProduct').click(function(){
		    window.history.back()
		});
	</script>
@stop