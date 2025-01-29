<?php

namespace Drupal\uls_resource;

use GuzzleHttp\Client;

/**
 * An ArchivesSpace authenticated session object.
 */
class ArchivesSpaceSession {

  /**
   * Connection Information.
   *
   * @var array
   */
  protected $connectionInfo = [
    'base_uri' => 'https://pittsbapi.as.atlas-sys.com',
    'username' => 'test',
    'password' => 'test',
  ];

  /**
   * Session ID.
   *
   * @var string
   */
  protected $session = '';

  /**
   * {@inheritdoc}
   */
  public function __construct() {
  }

  /**
   * Create a session with connection information.
   *
   * @param string $base_uri
   *   The base URI for the ArchivesSpace API.
   * @param string $username
   *   The username to use for authentication.
   * @param string $password
   *   The password to use for authentication.
   */
  public static function withConnectionInfo($base_uri, $username, $password) {
    if (!preg_match("@^https?://@", $base_uri)) {
      throw new \InvalidArgumentException('Could not connect with invalid base URI: ' . $base_uri);
    }
    if (empty($username) || empty($password)) {
      throw new \InvalidArgumentException('Could not connect. Either the username or password was missing.');
    }
    $instance = new self();
    $instance->connectionInfo = [
      'base_uri' => $base_uri,
      'username' => $username,
      'password' => $password,
    ];

    return $instance;
  }

  /**
   * Either logs in or returns the current session.
   *
   * @return ArchivesSpaceSession
   *   The ArchivesSpace session object
   */
  public function getSession() {
    if (empty($this->session)) {
      $this->login();
    }
    return $this->session;
  }

  /**
   * Issues an ArchivesSpace request.
   *
   * @param string $type
   *   The type of Request to issue (usually GET or POST)
   * @param string $path
   *   The API path to use for the request.
   * @param array $parameters
   *   Either GET query parameters or array to POST as JSON.
   * @param bool $binary
   *   Expect a binary response instead of json.
   *
   * @return mixed
   *   Either an array of response data OR
   *   a GuzzleHttp\Psr7\Stream if $binary is true.
   */
  public function request(string $type, string $path, array $parameters = [], $binary = FALSE, $xml_f = FALSE) {
    if (!in_array($type, ['GET', 'POST'])) {
      throw new \InvalidArgumentException('Cant\'t make an ArchivesSpace request with type: ' . $type);
    }
    if (empty($this->session)) {
      $this->login();
    }
    $client = new Client(['base_uri' => $this->connectionInfo['base_uri']]);

    $request_data = [
      'headers' => [
        'X-ArchivesSpace-Session' => $this->session,
        'content-type' => 'application/xml',
      ],
    ];

    switch ($type) {
      case 'GET':
        $request_data['query'] = $parameters;
        break;

      case 'POST':
        $request_data['json'] = $parameters;
    }

    $response = $client->request($type, $path, $request_data);

    return ($binary) ? $response->getBody() : ($xml_f ? $response->getBody()->getContents() : json_decode($response->getBody(), TRUE));
  }

  /**
   * Login to ArchivesSpace.
   */
  protected function login() {
    $state_config = [];
    foreach (\Drupal::state()->getMultiple(['archivesspace.base_uri',
      'archivesspace.username',
      'archivesspace.password',
    ]
                                          ) as $key => $value) {
      $new_key = substr($key, 14);
      if (!empty($value)) {
        $state_config[$new_key] = $value;
      }
    }
    $this->connectionInfo = array_replace($this->connectionInfo, $state_config);

    // Setup the client.
    $client = new Client([
      'base_uri' => $this->connectionInfo['base_uri'],
    ]);

    // Form the query string and make the call.
    $login_url = '/users/' .
                 rawurlencode($this->connectionInfo['username']) .
                 '/login?password=' .
                 rawurlencode($this->connectionInfo['password']);

    $response = $client->post($login_url);

    // Return the Session ID from the response.
    $login_response = json_decode($response->getBody(), TRUE);
    $this->session = $login_response['session'];
  }

}
