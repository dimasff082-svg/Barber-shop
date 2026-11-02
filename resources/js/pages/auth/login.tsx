import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Masuk staff" />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="email" className="text-xs font-semibold text-[#e8e0d0]">
                                    Email
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="admin@barberflow.com"
                                    className="min-h-11 rounded-lg border border-[#e5c07b]/35 bg-[#100e0a]/90 text-white placeholder:text-neutral-500 focus-visible:border-[#f3e5ab] focus-visible:ring-2 focus-visible:ring-[#e5c07b]/30"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password" className="text-xs font-semibold text-[#e8e0d0]">
                                        Kata sandi
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-xs text-[#e5c07b] hover:text-[#f3e5ab] hover:underline"
                                            tabIndex={5}
                                        >
                                            Lupa kata sandi?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Kata sandi"
                                    className="min-h-11 rounded-lg border border-[#e5c07b]/35 bg-[#100e0a]/90 text-white placeholder:text-neutral-500 focus-visible:border-[#f3e5ab] focus-visible:ring-2 focus-visible:ring-[#e5c07b]/30"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-2.5">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                    className="!size-4 !h-4 !w-4 !min-h-4 !min-w-4 rounded border-[#e5c07b]/60 data-[state=checked]:bg-[#e5c07b] data-[state=checked]:text-[#161410]"
                                />
                                <Label htmlFor="remember" className="text-xs text-[#d8cfbe] cursor-pointer">
                                    Ingat saya
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-1 min-h-11 w-full rounded-lg bg-gradient-to-r from-[#d4af37] via-[#e5c07b] to-[#d4af37] font-bold text-[#14120c] shadow-lg shadow-[#d4af37]/25 transition-all hover:brightness-110 active:scale-[0.99] cursor-pointer"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner className="text-[#14120c]" />}
                                Masuk
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-500">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = (page: React.ReactNode) => (
    <AuthLayout
        title="Masuk ke akun staff"
        description="Gunakan email dan kata sandi akun admin atau barber Anda."
    >
        {page}
    </AuthLayout>
);
