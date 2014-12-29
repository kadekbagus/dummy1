@extends('mobile-ci.layout')

@section('ext_style')
	{{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
	
	<div class="row padded">
		<div class="col-xs-12">
			<h4>Halaman tidak ditemukan, silahkan kembali ke halaman <a href="{{ url('/customer/home') }}">utama</a>.</h4>
		</div>
	</div>
@stop

@section('ext_script_bot')
	{{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
@stop