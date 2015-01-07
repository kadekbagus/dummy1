<header class="mobile-ci ci-header header-container">
  <div class="header-buttons-container">
    <ul class="buttons-list right">
      <li><span><i class="glyphicon glyphicon-barcode"></i></span></li>
      <li><a id="searchBtn"><span><i class="glyphicon glyphicon-search"></i></span></a></li>
      <li><a href="{{ url('/customer/catalogue') }}"><span class="glyphicon glyphicon-book"></span></a></li>
      <li>
        <a href="{{ url('/customer/cart') }}">
          <span id="shopping-cart-span">
            <span class="fa-1x fa-stack cart-qty ">
              <i class="fa fa-circle fa-stack-2x cart-circle"></i>
              <?php
                if(!is_null($cartdata->cart)){
                  if(is_null($cartdata->cart->total_item)) {
                    $cartnumber = 0;
                  } else {
                    if($cartdata->cart->total_item > 9){
                      $cartnumber = '9+';
                    } else {
                      $cartnumber = $cartdata->cart->total_item;
                    }
                  }
                } 
              ?>
              <strong class="fa-stack-1x" id="cart-number" data-cart-number="{{$cartnumber}}">
                @if(! is_null($cartdata->cart))
                  @if($cartdata->cart->total_item > 9)
                  {{ '9+' }}
                  @else
                  {{ $cartdata->cart->total_item }}
                  @endif
                @endif
              </strong>
            </span>
            <i id="shopping-cart" class="glyphicon glyphicon-shopping-cart"></i>
          </span>
        </a>
      </li>
      <li><a data-toggle="dropdown" aria-expanded="true"><span><i class="glyphicon glyphicon-cog"></i></span></a>
        <ul class="dropdown-menu" role="menu">
          <li class="complimentary-bg"><span><span class="glyphicon glyphicon-user"></span> {{ Lang::get('mobileci.page_title.my_account') }}</span></li>
          <li class="complimentary-bg"><a href="{{ url('/customer/transfer') }}"><span><span class="glyphicon glyphicon-shopping-cart"></span> {{ Lang::get('mobileci.page_title.transfercart') }}</span></a></li>
          <li class="complimentary-bg"><span><span class="glyphicon glyphicon-barcode"></span> {{ Lang::get('mobileci.page_title.customer_id') }}</span></li>
          <li class="complimentary-bg"><a href="{{ url('/customer/logout') }}"><span><span class="glyphicon glyphicon-off"></span> {{ Lang::get('mobileci.page_title.logout') }}</span></a></li>
        </ul>
      </li>
    </ul>
    <ul class="buttons-list">
      <li><a href="{{ url('/customer/home') }}"><span><i class="glyphicon glyphicon-home"></i></span></a></li>
      <li><a id="backBtn"><span><i class="fa fa-arrow-left"></i></span></a></li>
    </ul>
  </div>
  <div class="header-location-banner">
    <span>
      @if(is_null($page_title))
      {{ 'ORBIT' }}
      @else
      {{ $page_title }}
      @endif
    </span>
  </div>
</header>

