@foreach($data->records as $product)
    <div class="main-theme catalogue">
        <div class="row row-xs-height catalogue-top">
            <div class="col-xs-6 catalogue-img col-xs-height col-middle">
                <div>
                    <?php $x = 1;?>
                    @if($product->on_promo)
                    <div class="ribbon-wrapper-green ribbon{{$x}}">
                        <div class="ribbon-green">{{ Lang::get('mobileci.catalogue.promo_ribbon') }}</div>
                    </div>
                    <?php $x++;?>
                    @endif
                    @if($product->is_new)
                    <div class="ribbon-wrapper-red ribbon{{$x}}">
                        <div class="ribbon-red">{{ Lang::get('mobileci.catalogue.new_ribbon') }}</div>
                    </div>
                    <?php $x++;?>
                    @endif
                    @if($product->on_coupons)
                    <div class="ribbon-wrapper-yellow ribbon{{$x}}">
                        <div class="ribbon-yellow">{{ Lang::get('mobileci.catalogue.coupon_ribbon') }}</div>
                    </div>
                    <?php $x++;?>
                    @endif
                    @if($product->on_couponstocatch)
                    <div class="ribbon-wrapper-yellow-dash ribbon{{$x}}">
                        <div class="ribbon-yellow-dash">{{ Lang::get('mobileci.catalogue.coupon_ribbon') }}</div>
                    </div>
                    <?php $x++;?>
                    @endif
                </div>
                <div class="zoom-wrapper">
                    <div class="zoom"><a href="{{ asset($product->image) }}" data-featherlight="image"><img src="{{ asset('mobile-ci/images/product-zoom.png') }}"></a></div>
                </div>
                <a href="{{ asset($product->image) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($product->image) }}"></a>
            </div>
            <div class="col-xs-6 catalogue-detail bg-catalogue col-xs-height">
                <div class="row">
                    <div class="col-xs-12">
                        <h3>{{ $product->product_name }}</h3>
                    </div>
                    @if(! empty($product->product_code))
                    <div class="col-xs-12">
                        <h4>{{ $product->product_code }}</h4>
                    </div>
                    @endif
                    <div class="col-xs-12 price">
                        @if(count($product->variants) > 1)
                        <small>{{ Lang::get('mobileci.catalogue.starting_from') }}</small>
                        @endif
                        @if($product->on_promo)
                            <h3 class="currency currency-promo"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="strike formatted-num">{{ $product->min_price }}</span></h3>
                            <h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $product->priceafterpromo }}</span></h3>
                        @else
                        <h3 class="currency"><small>{{ $retailer->parent->currency_symbol }}</small> <span class="formatted-num">{{ $product->min_price }}</h3>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="row catalogue-control-wrapper">
            <div class="col-xs-6 catalogue-short-des ">
                <p>{{ $product->short_description }}</p>
            </div>
            <div class="col-xs-2 catalogue-control text-center">
                <div class="circlet btn-blue detail-btn">
                    <a href="{{ url('customer/product?id='.$product->product_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
                </div>
            </div>
            @if($retailer->parent->enable_shopping_cart == 'yes')
                @if(count($product->variants) <= 1)
                <div class="col-xs-2 col-xs-offset-1 catalogue-control price ">
                    <div class="circlet btn-blue cart-btn text-center">
                        <a class="product-add-to-cart" data-hascoupon="{{$product->on_coupons}}" data-product-id="{{ $product->product_id }}" data-product-variant-id="{{ $product->variants[0]->product_variant_id }}" >
                            <span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
                        </a>
                    </div>
                </div>
                @else
                <div class="col-xs-2 col-xs-offset-1 catalogue-control price">
                    <div class="circlet btn-blue cart-btn text-center">
                        <a class="product-add-to-cart" href="{{ url('customer/product?id='.$product->product_id.'#select-attribute') }}">
                            <span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
                        </a>
                    </div>
                </div>
                @endif
            @else
                <div class="col-xs-2 col-xs-offset-1 catalogue-control price">
                    <div class="circlet btn-blue cart-btn text-center btn-disabled">
                        <a class="product-add-to-cart">
                            <span class="link-spanner"></span><i class="fa fa-shopping-cart"></i>
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endforeach

@if(!$data->no_more)
<div class="catalogue-img more">
    <div class="row catalogue-top">
        <div class="col-xs-12">
            <button class="btn btn-info btn-block load-more" data-more-family-id="{{ $data->family_id }}" data-more-family-level="{{ $data->family_level }}" data-more-family-skip="{{ $next_skip }}">{{ Lang::get('mobileci.catalogue.load_more') }}</button>
        </div>
    </div>
</div>
@endif

@if($load_more == 'no')
<ul>
@if(! is_null($subfamilies))
    @foreach($subfamilies as $subfamily)
        <li data-family-container="{{ $subfamily->category_id }}" data-family-container-level="{{ $subfamily->category_level }}"><a class="family-a" data-family-id="{{ $subfamily->category_id }}" data-family-level="{{ $subfamily->category_level }}" data-family-isopen="0" ><div class="family-label">{{ $subfamily->category_name }} <i class="fa fa-chevron-circle-down"></i></div></a>
            <div class="product-list"></div>
        </li>
    @endforeach
@endif
</ul>
@endif
