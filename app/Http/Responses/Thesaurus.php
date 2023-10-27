<?php

namespace App\Http\Responses;

use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Responsable;

class Thesaurus implements Responsable
{
    /**
     * @var array
     */
    protected $thesaurus;

    /**
     * Thesaurus constructor.
     */
    public function __construct(array $thesaurus)
    {
        $this->thesaurus = $thesaurus;
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function toResponse(Request $request): Response
    {
        return response()->json(['data' => $this->thesaurus]);
    }
}
