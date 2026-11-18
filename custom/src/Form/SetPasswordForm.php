<?php

// Local addition for polls.digitalsme.eu, not part of upstream Pollaris.
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Two uses, told apart by the "current_password" option:
//   - an administrator setting someone else's password, with no current one
//     to prove, since the point is that it was lost;
//   - a person changing their own, which does require it, so that a borrowed
//     unlocked session cannot lock the owner out.

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

class SetPasswordForm extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['current_password'] === true) {
            $builder->add('current_password', Type\PasswordType::class, [
                'label' => new TranslatableMessage('password.current', [], 'digitalsme'),
                'constraints' => [new Assert\NotBlank()],
            ]);
        }

        $builder->add('password', Type\RepeatedType::class, [
            'type' => Type\PasswordType::class,
            'first_options' => [
                'label' => new TranslatableMessage('password.new', [], 'digitalsme'),
                'help' => new TranslatableMessage('password.help', [], 'digitalsme'),
            ],
            'second_options' => [
                'label' => new TranslatableMessage('password.repeat', [], 'digitalsme'),
            ],
            // A literal string, not a TranslatableMessage: Symfony resolves
            // invalid_message through the validators domain, and a file of
            // ours by that name would replace upstream's wholesale. Rendered
            // as a key otherwise.
            'invalid_message' => 'The two passwords do not match.',
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Length(min: 12),
            ],
        ]);

        $builder->add('submit', Type\SubmitType::class, [
            'label' => new TranslatableMessage('password.submit', [], 'digitalsme'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'current_password' => false,
        ]);

        $resolver->setAllowedTypes('current_password', 'bool');
    }
}
