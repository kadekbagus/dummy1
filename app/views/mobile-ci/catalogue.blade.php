@extends('mobile-ci.layout')

@section('ext_style')
	{{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
<div>
	<ul class="family-list">
		<li><div class="family-label">Men <i class="fa fa-chevron-circle-down"></i></div>
			<div class="main-theme catalogue">
				<div class="row row-xs-height catalogue-top">
					<div class="col-xs-6 catalogue-img col-xs-height col-middle coupon-wrapper">
						<div>
							<div class="ribbon-wrapper-yellow ribbon1st">
								<div class="ribbon-yellow">Promo</div>
							</div>
							<div class="ribbon-wrapper-red ribbon2nd">
								<div class="ribbon-red">New</div>
							</div>
						</div>
						<div class="zoom-wrapper">
							<div class="zoom"><a href="{{ asset('mobile-ci/images/products/product1.png') }}" data-featherlight="image"><img src="{{ asset('mobile-ci/images/product-zoom.png') }}"></a></div>
						</div>
						<a href="{{ asset('mobile-ci/images/products/product1.png') }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset('mobile-ci/images/products/product1.png') }}"></a>
					</div>
					<div class="col-xs-6 catalogue-detail bg-catalogue col-xs-height">
						<div class="row">
							<div class="col-xs-12">
								<h3>Polo Shirt</h3>
							</div>
							<div class="col-xs-12">
								<h4>Code : 32165487</h4>
							</div>					
							<div class="col-xs-6 price">
								<small>Starting From</small>
								<h3 class="">USD 50 </h3>
							</div>
							<div class="col-xs-6 catalogue-control price">
								<div class="circlet btn-blue pull-right">
									<img src="{{ asset('mobile-ci/images/cart-clear.png') }}" >
								</div>
							</div>
								
						</div>
					</div>
				</div>
				<div class="row catalogue-control-wrapper">
					<div class="col-xs-9 catalogue-short-des ">
						<p>This robe corrupts the soul of the user, but provides wisdom in return.</p>
					</div>
					<div class="col-xs-3 catalogue-control ">
						<div class="circlet btn-blue pull-right">
							<a href="{{ url('customer/product/1') }}"><img src="{{ asset('mobile-ci/images/detail-clear.png') }}" ></a>
						</div>
					</div>
				</div>
			</div>

			<div class="main-theme catalogue">
				<div class="row row-xs-height catalogue-top">
					<div class="col-xs-6 catalogue-img col-xs-height col-middle coupon-wrapper">
						<div>
							<div class="ribbon-wrapper-yellow ribbon1st">
								<div class="ribbon-yellow">Promo</div>
							</div>
							<div class="ribbon-wrapper-red ribbon2nd">
								<div class="ribbon-red">New</div>
							</div>
						</div>
						<div class="zoom-wrapper">
							<div class="zoom"><a href="{{ asset('mobile-ci/images/products/product5.jpg') }}" data-featherlight="image"><img src="{{ asset('mobile-ci/images/product-zoom.png') }}"></a></div>
						</div>
						<a href="{{ asset('mobile-ci/images/products/product5.jpg') }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset('mobile-ci/images/products/product5.jpg') }}"></a>
					</div>
					<div class="col-xs-6 catalogue-detail bg-catalogue col-xs-height">
						<div class="row">
							<div class="col-xs-12">
								<h3>One Piece Brotherhood Poster</h3>
							</div>
							<div class="col-xs-12">
								<h4>Code : 32165487</h4>
							</div>					
							<div class="col-xs-6 price">
								<small>Starting From</small>
								<h3 class="">USD 50 </h3>
							</div>
							<div class="col-xs-6 catalogue-control price">
								<div class="circlet btn-blue pull-right">
									<img src="{{ asset('mobile-ci/images/cart-clear.png') }}" >
								</div>
							</div>
								
						</div>
					</div>
				</div>
				<div class="row catalogue-control-wrapper">
					<div class="col-xs-9 catalogue-short-des ">
						<p>This robe corrupts the soul of the user, but provides wisdom in return.</p>
					</div>
					<div class="col-xs-3 catalogue-control ">
						<div class="circlet btn-blue pull-right">
							<img src="{{ asset('mobile-ci/images/detail-clear.png') }}" >
						</div>
					</div>
				</div>
			</div>
			<ul>
				<li><div class="family-label">Casual <i class="fa fa-chevron-circle-right"></i></div>
					<ul>
						<li><div class="family-label">T-Shirt <i class="fa fa-chevron-circle-right"></i></div>
							<ul>
								<li><div class="family-label">T-Shirt <i class="fa fa-chevron-circle-right"></i></div>
									<ul>
										<li><div class="family-label">T-Shirt <i class="fa fa-chevron-circle-right"></i></div></li>
									</ul>
								</li>
							</ul>
						</li>
					</ul>
				</li>	
			</ul>
		</li>
		<li><div class="family-label">Women <i class="fa fa-chevron-circle-right"></i></div></li>
		<li><div class="family-label">Kids <i class="fa fa-chevron-circle-right"></i></div></li>
	</ul>
</div>
@stop

@section('ext_script_bot')
	{{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
@stop