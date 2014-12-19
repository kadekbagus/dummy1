<!doctype html>
<html lang="en">
<head>

    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
   {{--css--}}
    <link rel="stylesheet" href=" {{ URL::asset('templatepos/css/main.css') }} ">
    <link rel="stylesheet" href="{{ URL::asset('templatepos/vendor/font-awesome-4.2.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ URL::asset('templatepos/vendor/progressjs/progressjs.css') }}">
   {{--js--}}

    <script src="{{ URL::asset('templatepos/vendor/jquery/dist/jquery.min.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/angular/angular.min.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/angular-animate/angular-animate.min.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/angular-local-storage/dist/angular-local-storage.min.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/angular-ui-bootstrap/ui-bootstrap-tpls-0.12.0.min.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/accounting/accounting.min.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/moment/moment.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/progressjs/progress.js') }}"></script>


    <script  data-main="{{ URL::asset('templatepos/js/main.js') }}" src="{{ URL::asset('templatepos/vendor/require/require.js') }}"></script>
     <!-- stylesheet -->
    {{--TODO:AGUNG: move style to main.ccss--}}
    <style type="text/css">
        [ng\:cloak], [ng-cloak], [data-ng-cloak], [x-ng-cloak], .ng-cloak, .x-ng-cloak {
            display: none !important;
        }
        .loading-visible{
            display:block;
        }
        .loading-invisible{
            display:none;
        }
    </style>

    <style>
    .header, .footer {  }
        .header img   { float: left;}
        .header h1    { float: left; margin: 0px; padding: 15px; }
        .login-status { margin: 0px; padding: 15px; float: right; }
    </style>
	<title></title>
</head>
<body>

    @yield('content')

</body>
</html>