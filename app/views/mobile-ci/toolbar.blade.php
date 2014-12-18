<header class="mobile-ci ci-header header-container">
  <div class="header-buttons-container">
    <ul class="buttons-list right">
      <li><span><i class="glyphicon glyphicon-barcode"></i></span></li>
      <li><a id="searchBtn"><span><i class="glyphicon glyphicon-search"></i></span></a></li>
      <li><a href="{{ url('/customer/catalogue') }}"><span class="glyphicon glyphicon-book"></span></a></li>
      <li><a href="{{ url('/customer/cart') }}"><span><i class="glyphicon glyphicon-shopping-cart"></i></span></a></li>
      <li><a data-toggle="dropdown" aria-expanded="true"><span><i class="glyphicon glyphicon-cog"></i></span></a>
        <ul class="dropdown-menu" role="menu">
          <li class="complimentary-bg"><span><span class="glyphicon glyphicon-user"></span> Akun Saya</span></li>
          <li class="complimentary-bg"><span><span class="glyphicon glyphicon-shopping-cart"></span> Transfer Keranjang Belanja</span></li>
          <li class="complimentary-bg"><span><span class="glyphicon glyphicon-barcode"></span> Customer ID</span></li>
          <li class="complimentary-bg"><span><span class="glyphicon glyphicon-off"></span> Log out</span></li>
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
      {{ 'HOME' }}
      @else
      {{ $page_title }}
      @endif
    </span>
  </div>
</header>

