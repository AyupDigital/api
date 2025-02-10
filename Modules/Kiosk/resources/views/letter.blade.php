<html>
<body>
<h1>Your Hounslow Connect Kiosk Results</h1>
<br>
<p>
Hello,
<br>
Thanks for using the Hounslow Connect Kiosk at {{ $location }} on {{ now()->format('d-M-Y') }}. You asked for the services that you shortlisted to be sent to you via postal letter. Information on these services are contained below.
<br>
Please consider giving us feedback on your experience which will take no longer than 1 minute. You can do this by visiting the following website on your phone or computer: survey
    <br>
    @foreach($services as $key => $value)
        <br>
        Service {{$key+1}} of {{$services->count()}}
        <br>
        ---
        <br>
        {{ $value->name }} - provided by {{ $value->organisation->name }}
        <br>
        {{ $value->intro }}
        <br><br>
        @if ($value->contact_phone)
        Phone: {{ $value->contact_phone}}
            <br>
        @endif
        @if ($value->contact_email)
            Email: {{$value->contact_email}}<br>
        @endif
        @if ($value->url)
         Website: {{$value->url}}<br>
        @endif
    @endforeach

    <br>
Thanks,
    <br>
Hounslow Connect Team
</p>
</body>
</html>