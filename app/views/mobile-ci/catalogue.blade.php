@extends('mobile-ci.layout')

@section('ext_style')
	{{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
<div>
	<ul class="family-list">
	@foreach($families as $family)
		<li data-family-container="{{ $family->category_id }}" data-family-container-level="{{ $family->category_level }}"><a class="family-a" data-family-id="{{ $family->category_id }}" data-family-level="{{ $family->category_level }}" data-family-isopen="0"><div class="family-label">{{ $family->category_name }} <i class="fa fa-chevron-circle-down"></i></div></a>
			<div class="product-list"></div>
		</li>
	@endforeach
	</ul>
</div>
<!-- <div>
	<ul class="family-list">
		<li><div class="family-label">Men <i class="fa fa-chevron-circle-down"></i></div>
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
</div> -->
@stop

@section('ext_script_bot')
	{{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
	{{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
	{{ HTML::script('mobile-ci/scripts/jquery.storageapi.min.js') }}
	<script type="text/javascript">
		$(document).ready(function(){

			$('.family-list').on('click', 'a.family-a', function(event){
				var families = [];
				var open_level = $(this).data('family-level');
				$('li[data-family-container-level="'+open_level+'"] .product-list').css('display','visible').slideUp('slow');
				$('li[data-family-container-level="'+open_level+'"] .family-label i').attr('class', 'fa fa-chevron-circle-down');
				$('li[data-family-container-level="'+open_level+'"] .family-a').data('family-isopen', 0);
				$('li[data-family-container-level="'+open_level+'"] .family-a').attr('data-family-isopen', 0);
				// $("div.product-list").html('');
				// $('.family-label > i').attr('class', 'fa fa-chevron-circle-down');
				// $("a").data('family-isopen', 0);

				if($(this).data('family-isopen') == 0){
					$(this).data('family-isopen', 1);
					$(this).attr('data-family-isopen', 1);

					var a = $(this);
					var family_id = $(this).data('family-id');
					var family_level = $(this).data('family-level');

					var aopen = $('a[data-family-isopen="1"]');
					
					$.each(aopen, function(index, value) {
						families.push($(value).attr('data-family-id'));
					});

					$.ajax({
						url: apiPath+'customer/products',
						method: 'GET',
						data: {
							families: families,
							family_id: family_id,
							family_level: family_level,
						}
					}).done(function(data){
						if(data == 'Invalid session data.'){
							location.replace('/customer');
						} else {
							a.parent('[data-family-container="'+ family_id +'"]').children("div.product-list").css('display', 'none').html(data).slideDown('slow');
							$('*[data-family-id="'+ family_id +'"] > .family-label > i').attr('class', 'fa fa-chevron-circle-up');
						}
					});
				} else {
					$(this).data('family-isopen', 0);
					$(this).attr('data-family-isopen', 0);
					var family_id = $(this).data('family-id');
					var family_level = $(this).data('family-level');
					$('*[data-family-container="'+ family_id +'"]').children("div.product-list").html('');
					$('*[data-family-id="'+ family_id +'"] > .family-label > i').attr('class', 'fa fa-chevron-circle-down');
				}
				
			});
			// add to cart
			$('.family-list').on('click', 'a.product-add-to-cart', function(event){
				var prodid = $(this).data('product-id');
				var img = $(this).children('img');
				var cart = $('#shopping-cart');
				$.ajax({
					url: apiPath+'customer/addtocart',
					method: 'POST',
					data: {
						productid: prodid,
						qty:1
					}
				}).done(function(data){
					// animate cart
					
					var imgclone = img.clone().offset({
						top: img.offset().top,
						left: img.offset().left
					}).css({
						'opacity': '0.5',
						'position': 'absolute',
						'height': '20px',
						'width': '20px',
						'z-index': '100'
					}).appendTo($('body')).animate({
						'top': cart.offset().top + 10,
						'left': cart.offset().left + 10,
						'width': '10px',
						'height': '10px',
					}, 1000);

					setTimeout(function(){
						cart.effect('shake', {
							times:2,
							distance:4,
							direction:'up'
						}, 200)
					}, 1000);

					imgclone.animate({
						'width': 0,
						'height': 0
					}, function(){
						$(this).detach();
						$('.cart-qty').css('display', 'block');
					    var cartnumber = parseInt($('#cart-number').attr('data-cart-number'));
					    cartnumber = cartnumber + 1;
					    if(cartnumber <= 9){
					    	$('#cart-number').attr('data-cart-number', cartnumber);
					    	$('#cart-number').text(cartnumber);
					    }else{
					    	$('#cart-number').attr('data-cart-number', '9+');
					    	$('#cart-number').text('9+');
					    }
					});

				});
			});
		});
	</script>
@stop