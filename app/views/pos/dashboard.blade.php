@extends('pos.layouts.default')
@section('content')

<div class="ng-cloak" ng-controller="dashboardCtrl">

    <div class="container-fluid" style="border-bottom:1px solid #c0c0c0">
            <div class="header">
                <img src=" {{ URL::asset('templatepos/images/logo_matahari.png') }}"  class="img" style="width: 64px">
                <h1>MATAHARI DEPARTMENT STORE</h1>
                <div class="btn-group "   style="float: right; padding-top: 40px; padding-left: 10px;padding-right: 20px" dropdown>

                     <% datauser.username %>&nbsp;<span class="down"  dropdown-toggle><i class="fa fa-caret-down"></i></span>
                      {{--<button type="button" class="btn btn-primary dropdown-toggle"style="background-color: #2c71a3;" dropdown-toggle>
                        <span class="caret"></span>
                        <span class="sr-only">Split button!</span>
                      </button>--}}
                       {{-- <i class="fa fa-sign-out"></i>--}}
                      <ul class="dropdown-menu" style="min-width: 60px" role="menu">
                        <li> <a href="#" data-ng-click="logoutfn()">Keluar</a></li>
                      </ul>
                </div>
                <p  style="float: right; padding-top: 40px;" >Guest  <% guests %> | <% datetime %></p>
            </div>
    </div>

    <div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="col-md-8">
                <div class="orbit-component table-attribute-top">
                    <div class="row">
                         <div class="col-md-6" style="margin-top: 6px"><h3>KERANJANG BELANJA</h3></div>
                         <div class="col-md-6 text-right"> <button class="btn btn-primary" style="background-color: #2c71a3;" data-ng-click="loginFn()" type="submit">SCAN KERANJANG</button></div>
                    </div>

                </div>
                <div class="table-responsive"  style="overflow: auto;height: 465px" >
                    <table class="table">
                        <tr>
                            <th class="text-center">NAMA + UPC</th>
                            <th class="text-center">JUMLAH</th>
                            <th class="text-center">HARGA TOTAL</th>
                        </tr>
                        <tbody>
                           <tr data-ng-repeat="(k,v) in cart">
                                <td style="max-width: 300px;word-wrap: break-word;" >
                                    <a href="" data-toggle="modal" data-backdrop="static" data-target="#myModal" data-ng-click="showdetailFn(v.idx,'fc')"><b> <% v.product_name %></b></a> <br><% v.upc_code %>
                                </td>
                                <td style="width: 200px">
                                    <div class="input-group ui-spinner" data-ui-spinner="">
                                          <span class="input-group-btn">
                                                                  <button type="button" class="btn btn-primary"  data-ng-click="qaFn($index,'m')" data-spin="up">
                                                                      <i class="fa fa-minus"></i>
                                                                  </button>
                                                              </span>
                                          <input type="text" class="spinner-input form-control"  data-ng-model="cart[k]['qty']" data-ng-change="qtychangemanualFn()" numbers-only="numbers-only" style="margin-top: 5px !important;">
                                          <span class="input-group-btn">
                                              <button type="button" class="btn btn-primary" data-spin="down" data-ng-click="qaFn($index,'p')">
                                                  <i class="fa fa-plus"></i>
                                              </button>
                                          </span>&nbsp;&nbsp;
                                          <span class="input-group-btn">
                                                <button type="button" class="btn btn-danger" data-ng-click="qaFn(k,'d')" >
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                          </span>
                                    </div>
                                </td>
                                <td class="text-right"><% v.hargatotal %></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <tr>
                            <td class="text-center"><b><h4>TOTAL ITEMS<br><% cart.totalitem %></b></h4></td>
                            <td class="text-center"><b><h4>SUBTOTAL<br><% cart.subtotal %></b></h4></td>
                            <td class="text-center"><b><h4>VAT<br><% cart.vat %></b></h4> </td>
                            <td class="text-center"><b><h4>TOTAL TO PAY<br><% cart.totalpay %></b></h4></td>
                        </tr>
                    </table>
                </div>
                <div class="table-responsive">
                    <table class="table  orbit-component table-noborder">
                        <tr>
                            <td colspan="3"> <button class="btn btn-danger" data-ng-disabled="cart.length == 0" data-toggle="modal" data-backdrop="static" data-target="#myModalNewCart" type="submit">KERANJANG BELANJA BARU</button> &nbsp; <button class="btn btn-primary" data-ng-disabled="cart.length == 0" style="background-color: #2c71a3;" data-toggle="modal" data-backdrop="static" data-target="#myModalDeleteCart"  type="submit">HAPUS KERANJANG BELANJA</button></td>
                            <td class="text-right"> <button class="btn btn-success" data-ng-disabled="cart.length == 0" style="background-color: #009933;" data-toggle="modal" data-backdrop="static" data-target="#myModalcheckout" data-ng-click="checkoutFn('b')" type="submit">BAYAR</button></td>
                       </tr>
                    </table>
                </div>
            </div>
            <div class="col-md-4">
                <div class="orbit-component table-attribute-top" >
                      <div class="row">
                          <div class="col-md-12"><h4 class="text-center">KATALOG PRODUK</h4><br>
                            <div class="input-group" id="loadingsearch">
                                     <div class="input-group-addon"><span class="glyphicon glyphicon-search" aria-hidden="true"></span></div>

                                     <input type="text" class="form-control"  data-ng-model="searchproduct" id="exampleInputEmail2" placeholder="Cari Produk">
                            </div>
                          </div>
                      </div>
                </div>
                <div class="orbit-component table-attribute-top" style="background-color: #B3B3B3;overflow: auto;height: 585px;overflow-x: hidden; padding-top: 1px" id="loading" >
                      <div class="row">
                      <div data-ng-if="productnotfound">
                           <p class="text-center"> Produk yang dicari tidak ditemukan </p>
                      </div>
                          <div class="col-md-6" data-ng-repeat="(k,v) in product" class="repeat-item">
                                <button ng-class="k % 2 == 0 ? 'btn mini-box ' : 'btn mini-boxright'" ng-disabled="v.disabled" data-toggle="modal" data-backdrop="static" data-target="#myModal" data-ng-click="showdetailFn(k)">
                                       <div class="row no-gutter">
                                          <div class="col-xs-4 col-xs-offset-1">
                                             	<div class="col-xs-12"><img src=" {{ URL::asset('templatepos/images/ss.jpg') }}"  class="img64_64"></div>
                                           </div>
                                           <div class="col-xs-6" >
                                           	   <div class="col-xs-12 text-left" style="margin-left: 13px;">
                                           	    <h5><b><% v.product_name.substr(0,9) %></b><br><b style="font-size: 10px"><% v.upc_code %></b></h5>
                                           	   </div>
                                           	   <div class="col-xs-12 text-right" style=""><h6 style="margin-top:1px"><% v.price %></h6></div>
                                           </div>
                                       </div>
                                </button>
                          </div>
                      </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal Product Detail-->
    <div class="modal fade" id="myModal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content" style="width: 400px;  margin: 30px auto;" >

          <div class="modal-body">
            <div class="row">
                <div class="col-md-12">
                <div class="ribbon-wrapper-red ribbon2nd">
                				<div class="ribbon-red">30%</div>
                			</div>
                    <p class="text-center"><img src=" {{ URL::asset('templatepos/images/ss.jpg') }}"  class="img product"></p>
                </div>
                <div class="col-md-12 main-theme" >
                    <div class="row">
                    	<div class="col-xs-12">
                    				<h4> <span class="orbit-component word-wrap"><% productmodal.product_name %></span></h4>
                    			</div>
                    	<div class="col-xs-12">
                    				<p><% productmodal.short_description %></p>
                    			</div>
                    </div>
                     <div class="additional-detail">
                     	<div class="row">
                     				<div class="col-xs-12">
                     					<h4>Discount Coupon Promo</h4>
                     				</div>
                     				<div class="col-xs-3 ">
                     					<h5>30%</h5>
                     				</div>
                     				<div class="col-xs-9">
                     					<h5>Happy Halloween</h5>
                     				</div>
                     			</div>
                     	<div class="row">
                     				<div class="col-xs-5 col-xs-height">
                     					<p>19 Sep 2014</p>
                     				</div>
                     				<div class="col-xs-2 col-xs-height">
                     					<p>s/d</p>
                     				</div>
                     				<div class="col-xs-5 col-xs-height">
                     					<p>31 Oct 2014</p>
                     				</div>
                     			</div>
                     </div>
                </div>
                <div class="col-md-12">
                    <div class="col-xs-12 product-attributes">
                    		<div class="row">
                    			<div class="col-xs-4 main-theme-text">
                    				<div class="radio-container">
                    					<h5>Size</h5>
                    				        <label class="ui-checkbox"><input name="checkbox1" type="checkbox" value="option1" ><span>Option</span></label>
                    				        <label class="ui-checkbox"><input name="checkbox1" type="checkbox" value="option2" checked><span>Option</span></label>

                    				</div>
                    			</div>
                    			<div class="col-xs-4 main-theme-text">
                    				<div class="radio-container">
                    					<h5>Colors</h5>
                    				      <label class="ui-checkbox"><input name="checkbox1" type="checkbox" value="option1" ><span>Option</span></label>
                                          <label class="ui-checkbox"><input name="checkbox1" type="checkbox" value="option2" checked=""><span>Option</span></label>

                    				</div>
                    			</div>
                    			<div class="col-xs-4 main-theme-text">
                    				<div class="radio-container">
                    					<h5>Sleeve</h5>
                    				      <label class="ui-checkbox"><input name="checkbox1" type="checkbox" value="option1" ><span>Option</span></label>
                                          <label class="ui-checkbox"><input name="checkbox1" type="checkbox" value="option2" checked=""><span>Option</span></label>
                    				</div>
                    			</div>
                    		</div>
                    	</div>
                </div>
                <div class="col-md-12  main-theme">
                    <div class="col-md-6">
                         <p>UPC :<% productmodal.upc_code %> </p>
                         <p><h5><del>300.000</del></h5></p>
                         <p><h4>IDR : <% productmodal.price %></h3></p>
                    </div>
                    <div class="col-md-6">
                         <p>&nbsp;</p>
                         <p class="text-center"><button type="button" class="btn btn-primary" data-dismiss="modal" style="background-color:#097494 ;padding-left: 20px; padding-right: 20px"><i class="fa fa-mail-reply"></i></button> &nbsp; <button type="button" data-ng-if="!hiddenbtn" class="btn btn-primary" style="background-color:#097494 ;padding-left: 20px; padding-right: 20px" data-ng-click="inserttocartFn()" data-dismiss="modal" ><i class="fa fa-shopping-cart"></i></button></p>
                    </div>
                 </div>
            </div>
          </div>

        </div>
      </div>


    </div>
    <!-- Modal Cart Baru-->
    <div class="modal fade" id="myModalNewCart" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
            <h4 class="modal-title" id="myModalLabel"><b>Keranjang Belanja Baru</b></h4>
          </div>
          <div class="modal-body">
                  <p><b>Anda yakin akan ingin membuat keranjang belanja baru ?</b></p>
                  <p>Setelah anda memilih "Ya", maka keranjang belanja baru akan menggantikan keranjang belanja sebelumnya </p>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal" style="background-color:#56BDF1;color:#2C71A3" data-ng-click="newcartFn()">Ya</button>
            <button type="button" class="btn btn-danger" data-dismiss="modal" >Tidak</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Hapus Cart-->
    <div class="modal fade" id="myModalDeleteCart" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
            <h4 class="modal-title" id="myModalLabel"><b>Hapus Keranjang Belanja</b></h4>
          </div>
          <div class="modal-body">
                  <p><b>Anda yakin akan ingin menghapus keranjang belanja ?</b></p>
                  <p>Setelah anda memilih "Ya", maka semua produk dalam keranjang belanja ini akan dihapus</p>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal" style="background-color:#56BDF1;color:#2C71A3" data-ng-click="deletecartFn()">Ya</button>
            <button type="button" class="btn btn-danger" data-dismiss="modal">Tidak</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal Checkout-->
    <div class="modal fade" id="myModalcheckout" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
            <h4 class="modal-title text-center" id="myModalLabel"><b>PILIH CARA PEMBAYARAN</b></h4>
          </div>
          <div class="modal-body text-center">
                  <p><button type="button" class="btn btn-success btn-lg" data-dismiss="modal" style="background-color: #009933; padding-left: 83px; padding-right: 83px " data-ng-click="checkoutFn('t')">TUNAI</button></p>
                  <p><button type="button" class="btn btn-success btn-lg" data-dismiss="modal" style="background-color: #009933; " data-ng-click="checkoutFn('k')">KARTU DEBIT/KREDIT</button> </p>

          </div>
        </div>
      </div>
    </div>

</div>
</div>

@stop

