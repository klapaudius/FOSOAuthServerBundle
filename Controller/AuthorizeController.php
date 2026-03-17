<?php

declare(strict_types=1);

/*
 * This file is part of the FOSOAuthServerBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\OAuthServerBundle\Controller;

use FOS\OAuthServerBundle\Event\OAuthEvent;
use FOS\OAuthServerBundle\Form\Handler\AuthorizeFormHandler;
use FOS\OAuthServerBundle\Model\ClientInterface;
use FOS\OAuthServerBundle\Model\ClientManagerInterface;
use OAuth2\OAuth2;
use OAuth2\OAuth2RedirectException;
use OAuth2\OAuth2ServerException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Controller handling basic authorization.
 *
 * @author Chris Jones <leeked@gmail.com>
 */
class AuthorizeController
{
    private ?ClientInterface $client = null;

    /**
     * This controller had been made as a service due to support symfony 4 where all* services are private by default.
     * Thus, this is considered a bad practice to fetch services directly from container.
     */
    public function __construct(
        private RequestStack $requestStack,
        private Form $authorizeForm,
        private OAuth2 $oAuth2Server,
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $router,
        private ClientManagerInterface $clientManager,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * Authorize.
     *
     * @throws OAuth2RedirectException
     */
    public function authorizeAction(Request $request, AuthorizeFormHandler $formHandler, Environment $twig): Response
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof UserInterface) {
            throw new AccessDeniedException('This user does not have access to this section.');
        }

        if (true === $request->getSession()->get('_fos_oauth_server.ensure_logout')) {
            $request->getSession()->invalidate(600);
            $request->getSession()->set('_fos_oauth_server.ensure_logout', true);
        }

        $form = $this->authorizeForm;

        /** @var OAuthEvent $event */
        $event = $this->eventDispatcher->dispatch(
            new OAuthEvent($user, $this->getClient()),
            OAuthEvent::PRE_AUTHORIZATION_PROCESS
        );

        if ($event->isAuthorizedClient()) {
            $scope = $request->query->get('scope');

            return $this->oAuth2Server->finishClientAuthorization(true, $user, $request, $scope);
        }

        if (true === $formHandler->process()) {
            return $this->processSuccess($user, $formHandler, $request);
        }

        $data = [
            'form' => $form->createView(),
            'client' => $this->getClient(),
        ];

        return $this->renderAuthorize($data, $twig);
    }

    protected function processSuccess(UserInterface $user, AuthorizeFormHandler $formHandler, Request $request): Response
    {
        if (true === $request->getSession()->get('_fos_oauth_server.ensure_logout')) {
            $this->tokenStorage->setToken(null);
            $request->getSession()->invalidate();
        }

        $this->eventDispatcher->dispatch(
            new OAuthEvent($user, $this->getClient(), $formHandler->isAccepted()),
            OAuthEvent::POST_AUTHORIZATION_PROCESS
        );

        $formName = $this->authorizeForm->getName();
        if (!$request->query->all() && $request->request->has($formName)) {
            $request->query->add($request->request->all($formName));
        }

        try {
            return $this->oAuth2Server
                ->finishClientAuthorization($formHandler->isAccepted(), $user, $request, $formHandler->getScope())
            ;
        } catch (OAuth2ServerException $e) {
            return $e->getHttpResponse();
        }
    }

    /**
     * Generate the redirection url when the authorize is completed.
     */
    protected function getRedirectionUrl(UserInterface $user): string
    {
        return $this->router->generate('fos_oauth_server_profile_show');
    }

    protected function getClient(): ClientInterface
    {
        if (null !== $this->client) {
            return $this->client;
        }

        $request = $this->getCurrentRequest();

        if (null === $clientId = $request->query->get('client_id')) {
            $formData = $request->request->all($this->authorizeForm->getName());
            $clientId = $formData['client_id'] ?? null;
        }

        $this->client = $this->clientManager->findClientByPublicId($clientId);

        if (null === $this->client) {
            throw new NotFoundHttpException('Client not found.');
        }

        return $this->client;
    }

    /**
     * @param array<string , mixed> $data Various data to be passed to the twig template
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    protected function renderAuthorize(array $data, Environment $twig): Response
    {
        $response = $twig->render(
            '@FOSOAuthServer/Authorize/authorize.html.twig',
            $data
        );

        return new Response($response);
    }

    private function getCurrentRequest(): Request
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            throw new \RuntimeException('No current request.');
        }

        return $request;
    }
}
