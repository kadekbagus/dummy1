<header class="mobile-ci ci-header header-container">
  <div class="header-buttons-container">
    <ul class="buttons-list right">
      <li><span><i class="glyphicon glyphicon-barcode"></i></span></li>
      <li><a id="searchBtn"><span><i class="glyphicon glyphicon-search"></i></span></a></li>
      <li><a href="{{ url('/customer/catalogue') }}"><span class="glyphicon glyphicon-book"></span></a></li>
      <li><a href="{{ url('/customer/cart') }}"><span><i class="glyphicon glyphicon-shopping-cart"></i></span></a></li>
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
  <div id="search-tool" style="display:none; background-color:#097494;">
    <div class="row" style="padding:15px;">
      <div class="col-xs-5">
        <label for="sort_by">Urutkan berdasarkan :</label>
        <select class="form-control" name="sort_by" id="sort_by">
          <option value="product_name">Nama</option>
          <option value="price">Harga</option>
        </select>
      </div>
      <div class="col-xs-5">
        <label for="sort_mode">Urutkan secara :</label>
        <select class="form-control" name="sort_mode" id="sort_mode">
          <option value="ASC">A-Z</option>
          <option value="DESC">Z-A</option>
        </select>
      </div>
      <div class="col-xs-2">
        <button class="form-control btn btn-success" type="button">Go</button>
      </div>
    </div>
  </div>
  <a href="{{ url('customer/home') }}" id="search-tool-close" style="background-color:#097494; display:block; width:20px; float:right; margin-top:-22px">
    <i class="glyphicon glyphicon-remove" style="color:#fff; "></i>
  </a>
  <div id="search-tool-btn" style="background-color:#097494; display:block; width:20px; float:right; margin-top:-22px; margin-right:24px;">
    <img class="img-responsive" src="{{ asset('mobile-ci/images/search-opt.png') }}">
  </div>
</header>

