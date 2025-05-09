@extends('layout')

@section('content')
<div class="govuk-grid-row">
    <div class="govuk-grid-column-one-half">
        <h1 class="govuk-heading-xl">Check your @if($method === 'sms')phone @elseif($method === 'email')email @endif</h1>
        <p class="govuk-body">
        @if($method === 'sms')
            We’ve sent you a text message with a security code to your registered number (ending {{ $phoneLastFour }}).
        @elseif($method === 'email')
            We’ve sent you an email with a security code to your registered email address ({{ $maskedEmail }}).    
        @endif</p>
        <form method="POST" action="{{ route('login.code') }}" novalidate>

            @csrf

            <div class="govuk-form-group {{ $errors->has('token') ? 'govuk-form-group--error' : '' }}">
                
                <label class="govuk-label govuk-label--m" for="token">
                    @if($method === 'sms')
                        Text message code
                    @elseif($method === 'email')
                        Email code
                    @endif
                </label>
                @if($errors->has('token'))
                <span class="govuk-error-message">
                    {{ $errors->first('token') }}
                </span>
                @endif
                <input class="govuk-input govuk-input--width-4" id="token" name="token" type="number" aria-describedby="token-hint">
                @if($method === 'sms')
                <p class="govuk-body"><a href="{{ $newNumberLink }}" class="govuk-link">New phone number?</a></p>
                @elseif($method === 'email')
                <p class="govuk-body"><a href="{{ $newNumberLink }}" class="govuk-link">New email address?</a></p>
                @endif
            </div>

            <button type="submit" class="govuk-button">
                Login
            </button>

        </form>
    </div>
</div>
@endsection
