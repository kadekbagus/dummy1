@extends('mobile-ci.layout')

@section('ext_style')
  <style type="text/css">
    .img-responsive{
      margin:0 auto;
    }
  </style>
@stop

@section('content')
  <div class="container">
      <div class="row">
        <div class="col-xs-12 text-center merchant-logo">
          <img class="img-responsive" src="{{ asset($retailer->parent->logo) }}" />
        </div>
      </div>
      <div class="mobile-ci home-widget widget-container">
        <div class="row">
          <div class="single-widget-container col-xs-6 col-sm-6">
            <header class="widget-title">
              <span>{{ Lang::get('mobileci.widgets.catalogue') }}</span>
            </header>
            <section class="widget-single">
              <ul class="rslides">
                @foreach($new_products as $new_product)
                <li>
                  <a href="{{ url('customer/product?id='.$new_product->product_id) }}">
                    <img class="img-responsive text-center" src="{{ asset('mobile-ci/images/products/product1.png') }}" />
                  </a>
                </li>
                @endforeach
              </ul>
            </section>
          </div>
          <div class="single-widget-container col-xs-6 col-sm-6">
            <header class="widget-title">
              <span>{{ Lang::get('mobileci.widgets.new_product') }}</span>
            </header>
            <section class="widget-single">
              <!-- Slideshow 4 -->
              <div class="callbacks_container">
                <ul class="rslides" id="slider1">
                  @foreach($new_products as $new_product)
                    <li>
                      <a href="{{ url('customer/product?id='.$new_product->product_id) }}">
                      @if(!is_null($new_product->image))
                        <img class="img-responsive" src="{{ asset($new_product->image) }}"/>
                      @else
                        <img class="img-responsive" src="{{ asset('mobile-ci/images/default-product.png') }}"/>
                      @endif
                      </a>
                    </li>
                  @endforeach
                </ul>
              </div>
            </section>
          </div>
        </div>
        <div class="row">
          <div class="single-widget-container col-xs-6 col-sm-6">
            <header class="widget-title">
              <span>{{ Lang::get('mobileci.widgets.promotion') }}</span>
            </header>
            <section class="widget-single">
              <ul class="rslides" id="slider2">
                @if(count($promo_products) > 1)
                  @foreach($promo_products as $promo_product)
                    @if($promo_product->promotion_type == 'product')
                    <li>
                      <a href="{{ url('customer/product?id='.$promo_product->product_id) }}">
                      @if(!is_null($promo_product->image))
                        <img class="img-responsive" src="{{ asset($promo_product->image) }}"/>
                      @else
                        <img class="img-responsive" src="{{ asset('mobile-ci/images/default-product.png') }}"/>
                      @endif
                      </a>
                    </li>
                    @endif
                  @endforeach
                @else
                  <li>
                    <img class="img-responsive" src="{{ asset('mobile-ci/images/default-product.png') }}"/>
                  </li>
                @endif
              </ul>
            </section>
          </div>
          <div class="single-widget-container col-xs-6 col-sm-6">
            <header class="widget-title">
              <span>{{ Lang::get('mobileci.widgets.coupon') }}</span>
            </header>
            <section class="widget-single">
              <ul class="rslides" id="slider2">
                @foreach($new_products as $new_product)
                <li>
                  <a href="{{ url('customer/product?id='.$new_product->product_id) }}">
                    <img class="img-responsive text-center" src="{{ asset('mobile-ci/images/products/product1.png') }}" />
                  </a>
                </li>
                @endforeach
              </ul>
            </section>
          </div>
        </div>
      </div>
    </div>
@stop

@section('modals')
  <!-- Modal -->
  <div class="modal fade" id="promoModal" tabindex="-1" role="dialog" aria-labelledby="promoModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
      <div class="modal-content">
        <div class="modal-header orbit-modal-header">
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Tutup</span></button>
          <h4 class="modal-title" id="promoModalLabel">Promosi</h4>
        </div>
        <div class="modal-body">
          <p id="promoModalText">
            @if(! is_null($promotion)) 
              @if(! is_null($promotion->image)) 
              <img class="img-responsive" src="{{ asset($promotion->image) }}"><br> 
              @endif
              <b>{{ $promotion->promotion_name }}</b> <br> 
              {{ $promotion->description }}
            @endif 
          </p>
        </div>
        <div class="modal-footer">
          <div class="pull-right"><button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button></div>
        </div>
      </div>
    </div>
  </div>
@stop

@section('ext_script_bot')
  {{ HTML::script('mobile-ci/scripts/responsiveslides.min.js') }}
  <script type="text/javascript">
    $(document).ready(function(){
        @if(! is_null($promotion))
          $('#promoModal').modal();
        @endif

        $("#slider1").responsiveSlides({
          auto: true,
          pager: false,
          nav: true,
          prevText: '<i class="fa fa-chevron-left"></i>',
          nextText: '<i class="fa fa-chevron-right"></i>',
          speed: 500
        });
        $("#slider2").responsiveSlides({
          auto: true,
          pager: false,
          nav: true,
          prevText: '<i class="fa fa-chevron-left"></i>',
          nextText: '<i class="fa fa-chevron-right"></i>',
          speed: 500
        });
    });
  </script>
@stop