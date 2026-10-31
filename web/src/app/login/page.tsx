import { LoginForm } from "./LoginForm";

export default function LoginPage() {
  return (
    <main className="mx-auto w-full mt-24 max-w-sm px-4">
      <h1 className="mb-6 text-2xl font-semibold">Sign in to PaidUp</h1>
      <LoginForm />
    </main>
  );
}
