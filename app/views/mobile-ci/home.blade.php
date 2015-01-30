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
            @foreach($widgets as $i => $widget)
                @if($i % 2 == 1)
                <div class="row">
                @endif

                @if($widget->widget_type == 'catalogue')
                  <div class="single-widget-container col-xs-6 col-sm-6">
                    <header class="widget-title">
                      <span>{{ Lang::get('mobileci.widgets.catalogue') }}</span>
                    </header>
                    <section class="widget-single">
                      <ul class="rslides">
                        <li>
                          <a href="{{ url('customer/catalogue') }}">
                            <img class="img-responsive text-center" src="{{ asset('uploads/catalogue.jpg') }}" />
                          </a>
                        </li>
                      </ul>
                    </section>
                  </div>
                @endif

                @if($widget->widget_type == 'new_product')
                  <div class="single-widget-container col-xs-6 col-sm-6">
                    <header class="widget-title">
                      <span>{{ Lang::get('mobileci.widgets.new_product') }}</span>
                    </header>
                    <section class="widget-single">
                      <!-- Slideshow 4 -->
                      <div class="callbacks_container">
                        <ul class="rslides" id="slider1">
                          @if(count($new_products) > 1)
                            @foreach($new_products as $new_product)
                              <li>
                                <a href="{{ url('customer/search?new=1#'.$new_product->product_id) }}">
                                @if(!is_null($new_product->image))
                                  <img class="img-responsive" src="{{ asset($new_product->image) }}"/>
                                @else
                                  <img class="img-responsive" src="{{ asset('mobile-ci/images/default-product.png') }}"/>
                                @endif
                                </a>
                              </li>
                            @endforeach
                          @else
                            <li>
                              <img id="emptyNew" class="img-responsive" src="{{ asset('mobile-ci/images/default-product.png') }}"/>
                            </li>
                          @endif
                        </ul>
                      </div>
                    </section>
                  </div>
                @endif

                @if($widget->widget_type == 'promotion')
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
                            <a href="{{ url('customer/search?promo=1#'.$promo_product->product_id) }}">
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
                          <img id="emptyPromo" class="img-responsive" src="{{ asset('mobile-ci/images/default-product.png') }}"/>
                        </li>
                      @endif
                    </ul>
                  </section>
                </div>
                @endif

                @if($widget->widget_type == 'coupon')
                <div class="single-widget-container col-xs-6 col-sm-6">
                  <header class="widget-title">
                    <span>{{ Lang::get('mobileci.widgets.coupon') }}</span>
                  </header>
                  <section class="widget-single">
                    <ul class="rslides" id="slider2">
                      <li>
                        <a @if(!empty($coupons)) href="{{ url('customer/search?coupon=1') }}" @else id="emptyCoupon" @endif>
                          <img class="img-responsive text-center" src="{{ asset('uploads/coupon.jpg') }}" />
                        </a>
                      </li>
                    </ul>
                  </section>
                </div>
                @endif
              
                @if($i % 2 == 1)
                </div>
                @endif

            @endforeach
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
          <h4 class="modal-title" id="promoModalLabel">Events</h4>
        </div>
        <div class="modal-body">
          <p id="promoModalText">
            @if(! empty($events)) 
                @if($events->event_type == 'link')
                  @if($events->link_object_type == 'product')
                    @if(! empty($events->image)) 
                    <a href="{{ url('customer/product?id='.$events->link_object_id1) }}">
                      <img class="img-responsive" src="{{ asset($events->image) }}">
                    </a>
                    <br> 
                    <b><a href="{{ url('customer/product?id='.$events->link_object_id1) }}">{{ $events->event_name }}</a></b> <br> 
                    {{ $events->description }}
                    @else
                    <a href="{{ url('customer/product?id='.$events->link_object_id1) }}">
                      <img class="img-responsive" src="{{ asset('mobile-ci/images/default_event.png') }}">
                    </a>
                    <br> 
                    <b><a href="{{ url('customer/product?id='.$events->link_object_id1) }}">{{ $events->event_name }}</a></b> <br> 
                    {{ $events->description }}
                    @endif
                  @elseif($events->link_object_type == 'promotion')
                    @if(! empty($events->image)) 
                    <a href="{{ url('customer/search?promoid='.$events->link_object_id1) }}">
                      <img class="img-responsive" src="{{ asset($events->image) }}">
                    </a>
                    <br> 
                    <b><a href="{{ url('customer/search?promoid='.$events->link_object_id1) }}">{{ $events->event_name }}</a></b> <br> 
                    {{ $events->description }}
                    @else
                    <a href="{{ url('customer/search?promoid='.$events->link_object_id1) }}">
                      <img class="img-responsive" src="{{ asset('mobile-ci/images/default_event.png') }}">
                    </a>
                    <br> 
                    <b><a href="{{ url('customer/search?promoid='.$events->link_object_id1) }}">{{ $events->event_name }}</a></b> <br> 
                    {{ $events->description }}
                    @endif
                  @endif

                @elseif($events->event_type == 'informative')
                    @if(! empty($events->image)) 
                    <img class="img-responsive" src="{{ asset($events->image) }}">
                    <br> 
                    <b>{{ $events->event_name }}</b> <br> 
                    {{ $events->description }}
                    @else
                    <img class="img-responsive" src="{{ asset('mobile-ci/images/default_event.png') }}">
                    <br> 
                    <b>{{ $events->event_name }}</b> <br> 
                    {{ $events->description }}
                    @endif
                @endif
            @endif 
          </p>
        </div>
        <div class="modal-footer">
          <div class="pull-right"><button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button></div>
        </div>
      </div>
    </div>
  </div>
  <!-- Modal -->
  <div class="modal fade" id="noModal" tabindex="-1" role="dialog" aria-labelledby="noModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
      <div class="modal-content">
        <div class="modal-header orbit-modal-header">
          <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Tutup</span></button>
          <h4 class="modal-title" id="noModalLabel"></h4>
        </div>
        <div class="modal-body">
          <p id="noModalText"></p>
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
        @if(! is_null($events))
          $('#promoModal').modal();
        @endif
        $('#emptyCoupon').click(function(){
          $('#noModalLabel').text('Info');
          $('#noModalText').text('Maaf, Anda belum memiliki kupon.');
          $('#noModal').modal();
        });
        $('#emptyNew').click(function(){
          $('#noModalLabel').text('Info');
          $('#noModalText').text('Maaf, tidak ada produk baru untuk saat ini.');
          $('#noModal').modal();
        });
        $('#emptyPromo').click(function(){
          $('#noModalLabel').text('Info');
          $('#noModalText').text('Maaf, tidak ada promosi untuk saat ini.');
          $('#noModal').modal();
        });
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