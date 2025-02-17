@php
$map = [
    "referral" => "This service is only accessible if you have a referral",
    "appointment" => "This service is only accessible by appointment",
    "drop_in" => "Drop-in during opening hours",
    "membership" => "This service is accessible if you have a membership",
];
@endphp
<html>

<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            /* max-width: 600px; */
        }

        .line {
            border-top: 2px dashed #000;
            width: 100%;
            /* position: absolute; */
            /* top: 50%; */
            z-index: -2;
        }
        h2 {
            padding: 0;
            margin: 0;
            font-size: 15px;
        }
    </style>
</head>

<body>
    <h1>Your shortlisted results</h1>
    <div class="line"></div>
    <div>
        @foreach($services as $key => $value)
        <br>
        <h2><b>{{ $value['name'] }}</b></h2>

        <b>Run by: </b>{{ $value['organisation']['name'] }}
        <br>        <br>
        {{ $value['intro'] }}
        <br><br>
        @if ($value['national'])
        <b>National:</b> @if($value['national']) This is a national service @else This is a local service @endif
        <br>
        @endif
        @if($value['attending_access'])
        <b>Access to this service:</b> {{ $map[$value['attending_access']] }}
        <br>
        @endif
        @if($value['attending_access'])
        <b>Service setting:</b> {{ ucfirst(str_replace("_", " ", $value['attending_type'])) }}
        <br>
        @endif
        <b>Cost:</b> @if($value['is_free']) Free @else {{ $value['fees_text'] }} @endif
        <br><br>
        @if ($value['contact_phone'])
        <b>Phone:</b> {{ $value['contact_phone']}}
        <br>
        @endif
        @if ($value['contact_email'])
        <b>Email:</b> {{$value['contact_email']}}
        <br>
        @endif
        @if ($value['url'])
        <p style="word-wrap: break-word; max-width: 600px;"><b>Website:</b> {{$value['url']}}</p>
        <br>
        @endif
        <br>
        @if(sizeof($value['locations']) > 0)
        <b>Where? </b>
        @endif
        @foreach($value['locations'] as $location)
        <br>
        
        @if ($location['name'])
        <br>
        <u>{{ $location['name'] }}</u>
        @endif
        <br>
        @if(!$location['name'])<u> @endif {{ $location['location']['address_line_1'] }} @if(!$location['name'])</u> @endif
        
        @if($location['location']['address_line_2'])
        <br>
        {{ $location['location']['address_line_2'] }}
        @endif
        @if($location['location']['address_line_3'])
        <br>
        {{ $location['location']['address_line_3'] }}
        @endif
        @if($location['location']['city'])
        <br>
        {{ $location['location']['city'] }}
        @endif
        @if($location['location']['postcode'])
        <br>
        {{ $location['location']['postcode'] }}
        @endif
        <br><br>
        @if(isset($location['formatted_opening_hours']))
            @foreach($location['formatted_opening_hours'] as $html)
                {!! $html !!}
                <br>
                @endforeach
        @endif
        @endforeach
        <br><br>
        <div class="line"></div>
        @endforeach

        <br>
        <div style="width: 100%; text-align: center;">
            <b>London Borough of Hounslow</b>
        </div>
    </div>
</body>

</html>