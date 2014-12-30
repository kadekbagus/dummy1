@extends('mobile-ci.layout')

@section('ext_style')
	{{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
<div>
	<ul class="family-list">
	@foreach($families as $family)
		<li data-family-container="{{ $family->category_id }}" data-family-isopen-list="0"><a class="family-a" data-family-id="{{ $family->category_id }}" data-family-level="{{ $family->category_level }}" data-family-isopen="0"><div class="family-label">{{ $family->category_name }} <i class="fa fa-chevron-circle-down"></i></div></a>
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
	{{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
	<script type="text/javascript">
		$(document).ready(function(){
			$('.family-list').on('click', 'a.family-a', function(event){
				// $("div.product-list").html('');
				// $('.family-label > i').attr('class', 'fa fa-chevron-circle-down');
				// $("a").data('family-isopen', 0);

				if($(this).data('family-isopen') == 0){
					$(this).data('family-isopen', 1);
					var a = $(this);
					var family_id = $(this).data('family-id');
					var family_level = $(this).data('family-level');
					$.ajax({
						url: apiPath+'customer/products',
						method: 'GET',
						data: {
							family_id: family_id,
							family_level: family_level
						}
					}).done(function(data){
						// $('.product-list').html(data);
						// console.log($(this).children("a").data('family-id'));
						a.parent('[data-family-container="'+ family_id +'"]').children("div.product-list").html(data);
						$('*[data-family-id="'+ family_id +'"] > .family-label > i').attr('class', 'fa fa-chevron-circle-up');
					});
				} else {
					$(this).data('family-isopen', 0);
					var family_id = $(this).data('family-id');
					var family_level = $(this).data('family-level');
					$('*[data-family-container="'+ family_id +'"]').children("div.product-list").html('');
					$('*[data-family-id="'+ family_id +'"] > .family-label > i').attr('class', 'fa fa-chevron-circle-down');
				}
				
			});
			$('a.news').click(function(){
				alert('asd');
			});
		});
	</script>
@stop