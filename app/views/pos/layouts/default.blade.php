<!doctype html>
<html>
<head>

    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
   {{--css--}}
    <link rel="stylesheet" href=" {{ URL::asset('templatepos/css/main.css') }} ">
    <link rel="stylesheet" href="{{ URL::asset('templatepos/vendor/font-awesome-4.2.0/css/font-awesome.min.css') }}">
   {{--js--}}

    <script src="{{ URL::asset('templatepos/vendor/jquery/dist/jquery.min.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/angular/angular.min.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/angular-animate/angular-animate.min.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/angular-local-storage/dist/angular-local-storage.min.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/angular-ui-bootstrap/ui-bootstrap-tpls-0.12.0.min.js') }}"></script>
   {{-- <script src="{{ URL::asset('templatepos/js/app.js') }}"></script>--}}
    <script  data-main="{{ URL::asset('templatepos/js/main.js') }}" src="{{ URL::asset('templatepos/vendor/require/require.js') }}"></script>
	<title></title>
</head>
<body>

    @yield('content')

</body>
</html>
