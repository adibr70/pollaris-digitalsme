<?php

// Local addition for polls.digitalsme.eu, not part of upstream Pollaris.
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// The form behind "Add a user". Deliberately unmapped: the password is
// hashed in the controller, so the plain value must never be set on the
// entity.

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Constraints as Assert;

class NewUserForm extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('username', Type\EmailType::class, [
            'label' => new TranslatableMessage('users.form.username', [], 'digitalsme'),
            'help' => new TranslatableMessage('users.form.username_help', [], 'digitalsme'),
            'trim' => true,
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Email(),
            ],
        ]);

        $builder->add('password', Type\PasswordType::class, [
            'label' => new TranslatableMessage('users.form.password', [], 'digitalsme'),
            'help' => new TranslatableMessage('users.form.password_help', [], 'digitalsme'),
            'constraints' => [
                new Assert\NotBlank(),
                // Long enough to resist guessing, short enough that nobody
                // writes it on a sticky note.
                new Assert\Length(min: 12),
            ],
        ]);

        $builder->add('administrator', Type\CheckboxType::class, [
            'label' => new TranslatableMessage('users.form.administrator', [], 'digitalsme'),
            'help' => new TranslatableMessage('users.form.administrator_help', [], 'digitalsme'),
            'required' => false,
        ]);

        $builder->add('submit', Type\SubmitType::class, [
            'label' => new TranslatableMessage('users.form.submit', [], 'digitalsme'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
