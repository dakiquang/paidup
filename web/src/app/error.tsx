"use client";

export default function ErrorPage({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <main className="mx-auto w-full mt-24 max-w-sm px-4">
      <h1 className="text-xl font-semibold">That didn&apos;t work</h1>
      <p className="mt-2 text-sm text-zinc-600">{error.digest ? "Something went wrong on our side." : error.message}</p>
      <button onClick={reset} className="mt-6 text-sm underline">
        Try again
      </button>
    </main>
  );
}
