<html>

<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 15px;
            /* max-width: 600px; */
        }
    </style>
</head>

<body>
    <p>{{ $address_line_1 }}</p>
    @if ($address_line_2 && $address_line_2 !== "")
    <p>{{ $address_line_2 }}</p>
    @endif

    <p>{{ $postcode }}</p>

    <br>
    <p>{{ now()->format('d-M-Y') }}</p>
    <br>
    <p>
        Hello {{ $name }},
    </p>
    <p><b>Your Hounslow Connect Kiosk Results</b></p>
    <p>
        <br>
        Thanks for using the Hounslow Connect Kiosk on {{ now()->format('d-M-Y') }}. You asked for the services that you shortlisted to be sent to you via postal letter. Information on these services are contained below.
        <br><br>
        Please consider giving us feedback on your experience which will take no longer than 1 minute. You can do this by visiting the following website on your phone or computer: survey
        <br>
        @foreach($services as $key => $value)
        <br>
    <h3 style="margin-bottom: 0px;">{{ $value->name }}</h3>
    <span style="color: #666666;"><b>Provided by</b> {{ $value->organisation->name }}</span>
    <br><br>
    {{ $value->intro }}
    <br><br>
    @if ($value->contact_phone)
    <b>Phone:</b> {{ $value->contact_phone}}
    <br>
    @endif
    @if ($value->contact_email)
    <b>Email:</b> {{$value->contact_email}}<br>
    @endif
    @if ($value->url)
    <b>Website:</b> {{$value->url}}<br>
    @endif
    @endforeach

    <br>
    Thanks,
    <br>
    Hounslow Connect Team
    </p>
</body>

</html>