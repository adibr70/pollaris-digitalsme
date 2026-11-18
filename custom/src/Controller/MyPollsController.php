<?php

// Local addition for polls.digitalsme.eu, not part of upstream Pollaris.
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Upstream has no web page listing the polls you created: /my only offers a
// form that mails you the list. This controller renders that list in the
// browser instead, for the signed-in user only.
//
// Scope: your own polls. Administrators keep /admin for every poll on the
// instance, so this page means the same thing for everyone.

namespace App\Controller;

use App\Repository;
use App\Utils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MyPollsController extends BaseController
{
    public function __construct(
        private readonly Repository\PollRepository $pollRepository,
    ) {
    }

    #[Route('/my/polls', name: 'my polls')]
    public function show(Request $request): Response
    {
        $user = $this->getUser();

        // security.yaml already requires ROLE_USER on this path; this guard
        // keeps the controller correct on its own, should that rule ever move.
        if ($user === null) {
            throw $this->createAccessDeniedException();
        }

        $email = $user->getUserIdentifier();

        // Polls carry no relation to accounts: the author is a plain text
        // field. We match on it, case-insensitively, which is why the
        // creation form pre-fills it from the signed-in user.
        $queryBuilder = $this->pollRepository->createQueryBuilder('p');
        $queryBuilder->where('ILIKE(p.authorEmail, :email) = TRUE');
        $queryBuilder->setParameter('email', $email);
        $queryBuilder->orderBy('p.createdAt', 'DESC');

        $pollsPagination = Utils\Pagination::paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
        );

        return $this->render('my/polls.html.twig', [
            'pollsPagination' => $pollsPagination,
            'userEmail' => $email,
        ]);
    }
}
