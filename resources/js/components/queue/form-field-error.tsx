export function FormFieldError({
    id,
    message,
}: {
    id: string;
    message?: string;
}) {
    if (!message) return null;

    return (
        <p id={id} className="mt-2 text-sm leading-6 text-danger" role="alert">
            {message}
        </p>
    );
}
