<?php

namespace MGierok\GraphqlClient\Classes;

use Exception;
use Illuminate\Support\Arr;
use MGierok\GraphqlClient\Enums\Format;
use MGierok\GraphqlClient\Enums\Request;
use MGierok\GraphqlClient\Classes\Mutator;

class Client extends Mutator {

    private $query;
    public $queryType;
    protected $token;
    public $variables = [];
    public $rawHeaders = [
        'Content-Type' => 'application/json',
        'User-Agent' => 'Laravel GraphQL client',
    ];
    public $context = [];

    public function __construct($endpoint)
    {
        $this->endpoint = $endpoint;
    }

    /**
     * Generate the Graphql query in raw format
     *
     * @return string
     */
    public function getRawQueryAttribute()
    {
        if (Request::RAW == $this->queryType) {
            $content = $this->query;
        }
        else {
            $content = "{$this->queryType} {{$this->query}}";
        }

        return <<<"GRAPHQL"
        {$content}
        GRAPHQL;
    }


    /**
     * Build the HTTP request
     *
     * @return resource
     */
    public function getRequestAttribute()
    {
        return stream_context_create(array_merge([
            'http' => [
                'method'  => 'POST',
                'content' => json_encode(['query' => $this->raw_query, 'variables' => $this->variables], JSON_NUMERIC_CHECK),
                'header'  => $this->headers,
            ]
        ], $this->context));
    }


    /**
     * Include authentication headers
     *
     * @return void
     */
    protected function includeAuthentication()
    {
        $auth_scheme = config('graphqlclient.auth_scheme');

        // Check if is a valid authentication scheme
        if (!array_key_exists($auth_scheme, config('graphqlclient.auth_schemes')))
        throw new Exception('Invalid Graphql Client Auth Scheme');

        // fill Authentication header
        $authToken = isset($this->token) ? $this->token : config('graphqlclient.auth_credentials');
        data_fill($this->rawHeaders, config('graphqlclient.auth_header'),
        config('graphqlclient.auth_schemes')[$auth_scheme].$authToken);
    }


    /**
     * Return Client headers formatted
     *
     * @return array
     */
    public function getHeadersAttribute()
    {
        // Include Authentication
        if(config('graphqlclient.auth_credentials') || isset($this->token)) {
            $this->includeAuthentication();
        }

        $formattedHeaders = [];
        foreach ($this->rawHeaders as $key => $value) {
            $formattedHeaders[] = $key . ': ' . $value;
        }

        return $formattedHeaders;
    }


    /**
     * Allow to append a new header to the client
     *
     * @return Client
     */
    public function header($key, $value)
    {
        $this->rawHeaders = array_merge($this->rawHeaders, [
            $key => $value
        ]);

        return $this;
    }


    /**
     * Allow to append a new context info to the client
     *
     * @return Client
     */
    public function context($context)
    {
        $this->context = $context;
        return $this;
    }


    /**
     * Allow to pass multiple headers to the client
     *
     * @return Client
     */
    public function withHeaders($headers)
    {
        $this->rawHeaders = array_merge($this->rawHeaders, $headers);

        return $this;
    }


    /**
     * Allow to pass multiples variables to the client
     *
     * @return Client
     */
    public function with($variables)
    {
        $this->variables = array_merge($this->variables, $variables);

        return $this;
    }


    /**
     * Build a new client
     *
     * @return Client
     */
    private function generate($type, $query)
    {
        $this->queryType = $type;
        $this->query = $query;

        return $this;
    }


    /**
     * Build a new Graphql Query request
     *
     * @return Client
     */
    public function query($query)
    {
        return $this->generate(Request::QUERY, $query);
    }


    /**
     * Build a new Graphql Mutation request
     *
     * @return Client
     */
    public function mutation($query)
    {
        return $this->generate(Request::MUTATION, $query);
    }


    /**
     * Build a new Graphql Raw request
     *
     * @return Client
     */
    public function raw($query)
    {
        return $this->generate(Request::RAW, $query);
    }


    /**
     * Allow to change an request endpoint
     *
     * @return Client
     */
    public function endpoint($endpoint)
    {
        $this->endpoint = $endpoint;

        return $this;
    }


    /**
     * Execute request
     *
     * @return array
     */
    public function makeRequest($format, $rawResponse = false)
    {
        try {
            $result = file_get_contents($this->endpoint, false, $this->request);
            if ($format == Format::JSON) {
                $response = json_decode($result, false);
                if ($rawResponse) return $response;
                return $response->data;
            } else {
                $response = json_decode($result, true);
                if ($rawResponse) return $response;
                return Arr::get($response, "data");
            }

        } catch (\Throwable $th) {
            throw $th;
        }
    }


    /**
     * Return data
     * @param $format String (array|json) define return format, array by default
     *
     * @return array by default
     */
    public function get($format = Format::ARRAY)
    {
        return $this->makeRequest($format);
    }

    /**
     * Return raw response
     * @param $format String (array|json) define return format, array by default
     *
     * @return array by default
     */
    public function getRaw($format = Format::ARRAY)
    {
        return $this->makeRequest($format, true);
    }

}
