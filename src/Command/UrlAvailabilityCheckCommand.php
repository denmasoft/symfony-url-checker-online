<?php

namespace App\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

class UrlAvailabilityCheckCommand extends Command
{
    protected static $defaultName = 'app:check-url';
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Check if a URL is valid and online')
            ->addArgument('url', InputArgument::REQUIRED, 'URL to check')
            ->addOption('timeout', null, InputArgument::OPTIONAL, 'Connection timeout in seconds', 10);
    }

    /**
     * Validate URL with multiple checks
     */
    private function validateUrl(string $url): array
    {
        $validator = Validation::createValidator();

        // Comprehensive URL validation constraints
        $constraints = [
            // Basic URL format validation
            new Assert\Url([
                'protocols' => ['http', 'https'],
                'message' => 'The URL must be a valid HTTP or HTTPS URL.'
            ]),

            // Additional custom validation
            new Assert\Callback(function ($value, $context) {
                // Check for minimum length
                if (strlen($value) < 8) {
                    $context->buildViolation('URL is too short.')
                        ->addViolation();
                }

                // Prevent localhost and private network URLs in production
                $parsedUrl = parse_url($value);
                $host = $parsedUrl['host'] ?? '';

                $privateNetworks = [
                    'localhost',
                    '127.0.0.1',
                    '::1',
                    '192.168.',
                    '10.',
                    '172.16.'
                ];

                foreach ($privateNetworks as $network) {
                    if (strpos($host, $network) !== false) {
                        $context->buildViolation('Private network URLs are not allowed.')
                            ->addViolation();
                        break;
                    }
                }

                // Ensure the URL doesn't contain suspicious characters
                if (preg_match('/[<>"\'\(\)]/', $value)) {
                    $context->buildViolation('URL contains invalid characters.')
                        ->addViolation();
                }
            })
        ];

        // Perform validation
        $violations = $validator->validate($url, $constraints);

        // Convert violations to array of error messages
        $errors = [];
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }
        }

        return $errors;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = $input->getArgument('url');
        $timeout = $input->getOption('timeout');

        // Validate URL first
        $validationErrors = $this->validateUrl($url);
        if (!empty($validationErrors)) {
            $io->error("URL Validation Failed:");
            $io->listing($validationErrors);
            return Command::FAILURE;
        }

        try {
            // Custom headers to mimic browser request and potentially bypass some protections
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ];
            $headers['Referer'] = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);

            $response = $this->httpClient->request('GET', $url, [
                'timeout' => $timeout,
                'headers' => $headers,
                'verify_peer' => false,
                'verify_host' => false,
            ]);

            $statusCode = $response->getStatusCode();

            // Check for potential Cloudflare or captcha challenges
            $content = $response->getContent();
            $isCloudflareChallenge = strpos($content, 'Cloudflare') !== false
                || strpos($content, 'captcha') !== false;

            if ($isCloudflareChallenge) {
                $io->warning('Potential Cloudflare or Captcha protection detected!');
            }

            $io->success([
                "URL is valid and online!",
                "Status Code: $statusCode",
                $isCloudflareChallenge ? "Warning: Challenge page detected" : ""
            ]);

            return Command::SUCCESS;
        } catch (TransportException $e) {
            $io->error([
                "URL is not accessible",
                "Error: " . $e->getMessage()
            ]);
            return Command::FAILURE;
        } catch (
            Exception
            | TransportExceptionInterface
            | ClientExceptionInterface
            | RedirectionExceptionInterface
            | ServerExceptionInterface $e
        ) {
            $io->error([
                "Unexpected error occurred",
                "Error: " . $e->getMessage()
            ]);
            return Command::FAILURE;
        }
    }
}
