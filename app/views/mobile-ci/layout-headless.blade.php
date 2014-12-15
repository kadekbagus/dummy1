<!doctype html>
<html>
	<head>
		@include('mobile-ci.head')
		@yield('ext_style')
	</head>
	<body>
		<div class="container">
      		@yield('content')
    	</div>
    	@yield('modals')
    	@yield('ext_script_bot')
	</body>
</html>
