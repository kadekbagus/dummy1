@extends('pos.layouts.default')
@section('content')

<div class="ng-cloak" ng-controller="dashboardCtrl">

    <div class="container-fluid" style="border-bottom:1px solid #c0c0c0">
            <div class="header">
                <img ng-src="<% configs.baseUrlServerPublic %>/<% datauser['merchants'][0]['logo'] %>" class="img" style="height: 64px">
                <h1>MATAHARI DEPARTMENT STORE</h1>
                <div class="btn-group "   style="float: right; padding-top: 40px; padding-left: 10px;padding-right: 20px;color:#46c2ff" dropdown>
                     <% $parent.datauser.username %>&nbsp;<span class="down"  dropdown-toggle><i class="fa fa-caret-down"></i></span>
                      <ul class="dropdown-menu" style="min-width: 60px" role="menu">
                        <li> <a href="#" data-ng-click="logoutfn()">Keluar</a></li>
                      </ul>
                </div>
                <p  style="float: right; padding-top: 40px;color:#030000" >Guest  <% guests %> | <% $parent.datetime %></p>
            </div>
    </div>

    <div class="container-fluid" style="padding-top:0px !important">
        <div class="row" >

            <div class="col-md-7" style="margin-left: -8px">
                <div class="orbit-component table-attribute-top">
                    <div class="row">
                         <div class="col-md-6" style="margin-top: 6px"><h4>KERANJANG BELANJA</h4></div>
                         <div class="col-md-6 text-right"> <button class="btn btn-primary" style="background-color: #2c71a3;" data-ng-click="loginFn()" type="submit">SCAN KERANJANG</button></div>
                    </div>

                </div>
                <div class="table-responsive"  id="tablecart" style="overflow: auto;height: 380px" >
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
                                          <input type="text" pattern="[0-9]*" class="spinner-input form-control"  data-ng-model="cart[k]['qty']" data-ng-change="qtychangemanualFn()" numbers-only="numbers-only" style="margin-top: 5px !important;">
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

                            <tr>
                                 <td class="tdnoborder"></td>
                                 <td class="tdnoborder"></td>
                                 <td class="tdnoborder"></td>
                            </tr>
                            <tr id="bottom">
                                 <td class="tdnoborder"></td>
                                 <td class="tdnoborder"></td>
                                 <td class="tdnoborder"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <tr>
                            <td class="text-center"><b><h5>TOTAL ITEMS<br><% cart.totalitem %></b></h5></td>
                            <td class="text-center"><b><h5>SUBTOTAL<br><% cart.subtotal %></b></h5></td>
                            <td class="text-center"><b><h5>VAT<br><% cart.vat %></b></h5> </td>
                            <td class="text-center"><b><h5>TOTAL TO PAY<br><% cart.totalpay %></b></h5></td>
                        </tr>
                    </table>
                </div>
                <div class="table-responsive">
                    <table class="table  orbit-component table-noborder">
                        <tr>
                            <td colspan="3"> <button class="btn btn-danger" data-ng-disabled="cart.length == 0" data-toggle="modal" data-backdrop="static" data-target="#myModalNewCart" type="submit">KERANJANG BELANJA BARU</button> &nbsp; <button class="btn btn-primary" data-ng-disabled="cart.length == 0" style="background-color: #2c71a3;" data-toggle="modal" data-backdrop="static" data-target="#myModalDeleteCart"  type="submit">HAPUS KERANJANG BELANJA</button></td>
                            <td class="text-right"> <button class="btn btn-success" data-ng-disabled="cart.length == 0" style="background-color: #009933;" data-toggle="modal" data-backdrop="static" data-target="#myModalcheckout" type="submit">BAYAR</button></td>
                       </tr>
                    </table>
                </div>
            </div>
            <div class="col-md-5" style="padding-left: 0px;padding-right: 0px;margin-right: -50px">
                <div class="orbit-component table-attribute-top" >
                      <div class="row">
                          <div class="col-md-12"><h4 class="text-center">KATALOG PRODUK</h4><br>
                              <div class="input-group" id="loadingsearch">
                                   <div class="input-group-addon"><span class="glyphicon glyphicon-search" aria-hidden="true"></span></div>
                                        <input type="text" class="form-control"  data-ng-model="searchproduct" id="exampleInputEmail2" placeholder="Cari Produk">
                                   <div class="input-group-addon" style="background-color : #D60000; border: none;cursor:pointer" data-ng-click="resetsearch()"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span></div>
                              </div>
                           </div>
                      </div>
                </div>
                <div class="orbit-component table-attribute-top" style="background-color: #B3B3B3;overflow: auto;height: 495px;overflow-x: hidden; padding-top: 1px" id="loading" >
                      <div class="row">
                      <div data-ng-if="productnotfound">
                           <p class="text-center" style="padding-top: 20px; font-size: 16px"> Produk yang dicari tidak ditemukan </p>
                      </div>
                          <div class="col-md-6" data-ng-repeat="(k,v) in product" class="repeat-item">
                                <button ng-class="k % 2 == 0 ? 'btn mini-box ' : 'btn mini-boxright'" ng-disabled="v.disabled" data-toggle="modal" data-backdrop="static" data-target="#myModal" data-ng-click="showdetailFn(k)">
                                       <div class="row no-gutter" >
                                          <div class="col-xs-4 col-xs-offset-1">
                                             	<div class="col-xs-12"><img ng-src="<% configs.baseUrlServerPublic %>/<% v.image %>"  class="img64_64"></div>
                                           </div>
                                           <div class="col-xs-6">
                                           	   <div class="col-xs-12 text-left" style="margin-left: 3px;margin-top:-9px; ">
                                           	    <h5><b class="colorbold"><% v.product_name.substr(0,12) %></b><br><b style="font-size: 10px"><% v.upc_code.substr(0,12) %></b></h5>
                                           	   </div>
                                           	   <div class="col-xs-12 text-right"><h6 style="margin-top:1px"><% v.price %></h6></div>
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
             <div class="modal-header"  data-ng-if="hiddenbtn">
                 <button class="btn  close closemodal"  data-dismiss="modal" type="button">
                    <span class="glyphicon glyphicon-remove" aria-hidden="true"></span>
                 </button>
                   <h4 class="modal-title text-center" id="myModalLabel"><b>PRODUK DETAIL</b></h4>
             </div>
          <div class="modal-body">
            <div class="row">
                <div class="col-md-12">
                <div class="ribbon-wrapper-red ribbon2nd">
                				<div class="ribbon-red">30%</div>
                			</div>
                    <p class="text-center"><img ng-src="<% configs.baseUrlServerPublic %>/<% productmodal.image %>"  class="img product"></p>
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
                     			<div class="row" data-ng-if="hiddenbtn">
                     			    <div class="col-xs-12">
                                       <p><h5><del>300.000</del></h5></p>
                                       <p><h4>IDR : <% productmodal.price %></h3></p>
                                    </div>
                     			</div>
                     </div>
                </div>
                <div class="col-md-12" data-ng-if="!hiddenbtn">
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
                <div class="col-md-12  main-theme" data-ng-if="!hiddenbtn">
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
    <!-- Modal Product Not found-->
    <div class="modal fade" id="ProductNotFound" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
            <h4 class="modal-title" id="myModalLabel">&nbsp;</h4>
          </div>
          <div class="modal-body">
                  <p class="text-center"><b>Produk tidak ditemukan</b></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-dismiss="modal" >Close</button>
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
            <button type="button" class="btn btn-default" data-dismiss="modal" style="background-color:#56BDF1;color:#2C71A3" data-ng-click="newdeletecartFn(true)">Ya</button>
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
            <button type="button" class="btn btn-default" data-dismiss="modal" style="background-color:#56BDF1;color:#2C71A3" data-ng-click="newdeletecartFn()">Ya</button>
            <button type="button" class="btn btn-danger" data-dismiss="modal">Tidak</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal Checkout-->
    <div class="modal fade" id="myModalcheckout" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content" style="width: 400px;  margin: 30px auto;">
          <div class="modal-header">
             <button class="btn  close closemodal" data-ng-if="action != 'done'" data-dismiss="modal" data-ng-click="gotomain()"type="button">
              <span class="glyphicon glyphicon-remove" aria-hidden="true"></span>
             </button>
            <h4 class="modal-title text-center" id="myModalLabel"><b data-ng-init="cheader = 'PILIH CARA PEMBAYARAN'"> <% cheader %></b></h4>
          </div>
          <div class="modal-body text-center">

                   <div class="row" data-ng-init="action = 'main'" data-ng-show="action == 'main'">
                          <p><button type="button" class="btn btn-success btn-lg"  style="background-color: #009933; padding-left: 83px; padding-right: 83px " data-ng-click="checkoutFn('t')">TUNAI</button></p>
                          <p><button type="button" class="btn btn-success btn-lg"  style="background-color: #009933; padding-left: 58px; padding-right: 58px" data-ng-click="checkoutFn('k','Terminal 1')">TERMINAL 1</button> </p>
                          <p><button type="button" class="btn btn-success btn-lg"  style="background-color: #009933; padding-left: 58px; padding-right: 58px" data-ng-click="checkoutFn('k','Terminal 2')">TERMINAL 2</button> </p>
                          <p><button type="button" class="btn btn-success btn-lg"  style="background-color: #009933; padding-left: 58px; padding-right: 58px" data-ng-click="checkoutFn('k','Terminal 3')">TERMINAL 3</button> </p>
                   </div>
                   <div class="row" ng-show="action == 'cash'">
                                <div data-ng-keypad="numeric" data-auto-close="true" data-ng-draggable class="numpad">
                                     <div class="button-wrapper">
                                         <button data-ng-click="keypadFn('1')">1</button>
                                         <button data-ng-click="keypadFn('2')">2</button>
                                         <button data-ng-click="keypadFn('3')">3</button>
                                         <button data-ng-click="keypadFn('4')">4</button>
                                         <button data-ng-click="keypadFn('5')">5</button>
                                         <button data-ng-click="keypadFn('6')">6</button>
                                         <button data-ng-click="keypadFn('7')">7</button>
                                         <button data-ng-click="keypadFn('8')">8</button>
                                         <button data-ng-click="keypadFn('9')">9</button>


                                         <button data-ng-key="[CLEAR]" class="smaller">Clear</button>
                                         <button data-ng-key="0">0</button>
                                         <button data-ng-key=".">.</button>

                                         <button data-ng-key="[PREVIOUS]" class="button-wide smaller">Previous</button>
                                         <button data-ng-key="[NEXT]" class="button-wide smaller">Next</button>
                                     </div>
                                     <div class="drag-indicator">
                                         <span></span>
                                         <span></span>
                                         <span></span>
                                     </div>
                                     <button class="close" data-ng-click="close()"></button>
                                 </div>
                               <div class="form" style="padding-left: 20px;padding-right: 20px">
                                         <div class="form-group text-left" >
                                             <label for="exampleInputEmail1">Total bayar</label>
                                             <input type="text" class="form-control text-right"  id="exampleInputEmail1" style="cursor: default; color:#030000" disabled data-ng-model="cart.totalpay" placeholder="Total bayar">
                                         </div>
                                         <div data-ng-class="change < 0 ? 'form-group text-left has-error' : 'form-group text-left'">
                                             <label for="exampleInputEmail1">Nominal Tunai</label>
                                             <input type="text" class="form-control text-right" id="tenderedcash"  pattern="[0-9]*"  autofocus="autofocus" data-ng-keypad-input="numeric" tabindex="1"  numbers-only="numbers-only"  data-ng-model="cart.amount" placeholder="Nominal Tunai">
                                         </div>
                                         <div class="form-group text-left">
                                             <label for="exampleInputEmail1">Kembalian</label>
                                             <input type="text" class="form-control text-right" id="exampleInputEmail1" style="cursor: default;color:#030000" disabled data-ng-model="cart.change" placeholder="Kembalian">
                                          </div>
                               </div>

                   </div>
                   <div class="row" ng-show="action == 'card'">

                    <div class="row">
                         <span  class="text-center" data-ng-if="cardfile"><% headrcard %> failed</span>
                         <span  class="text-center" data-ng-if="!cardfile">Gesek Kartu Sekarang</span>
                    </div>
                </div>
                   <div class="row" ng-show="action == 'done'">
                         <p><button type="button" class="btn btn-primary btn-lg"  style="background-color: #2c71a3;" data-ng-click="ticketprint()">CETAK STRUK</button></p>
                         <p><button type="button" class="btn btn-success btn-lg" data-dismiss="modal"  style="background-color: #009933; padding-left: 53px; padding-right: 53px"   data-ng-click="checkoutFn('d')">DONE</button> </p>
                   </div>
          </div>
          <div class="modal-footer" data-ng-if="action !='main'">
                     <button type="button"  data-ng-if="cardfile && action !='done" class="btn btn-primary"  style="background-color: #2c71a3;" data-ng-click="checkoutFn('k')">RETRY</button>
                     <button type="button"  data-ng-if="action !='done'" class="btn btn-danger"  data-ng-click="gotomain()">Cancel</button>
                     <button type="button"  data-ng-if="action =='cash'" data-ng-disabled="!changetf" data-ng-init="change = 0" data-ng-click="checkoutFn('c')" class="btn btn-success" style="background-color: #009933;">Continue</button>
           </div>
        </div>
      </div>
    </div>

</div>
</div>

@stop

