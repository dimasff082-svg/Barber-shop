export type QueueStatus =
    | 'waiting_checkin'
    | 'checked_in'
    | 'called'
    | 'in_service'
    | 'completed'
    | 'missed'
    | 'cancelled';
export type QueueEstimate = {
    ahead: number;
    minutes_min: number | null;
    minutes_max: number | null;
    label: string;
};
export type CustomerTicket = {
    id: string;
    queue_number: string;
    queue_date: string;
    status: QueueStatus;
    source: 'online' | 'onsite';
    customer_name: string;
    barber: { id: number; name: string };
    service: { id: number; name: string };
    price: string;
    duration_minutes: number;
    grace_expires_at: string | null;
    checked_in_at: string | null;
    estimate: QueueEstimate;
};
export type PublicService = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price: string;
    duration_minutes: number;
    image: string | null;
};
export type PublicBarber = {
    id: number;
    name: string;
    specialization: string | null;
    bio: string | null;
    photo: string | null;
    availability: 'available' | 'busy' | 'break' | 'off_duty';
    waiting_count: number;
    registration: { allowed: boolean; reason: string | null };
    schedules: { weekday: number; starts_at: string; ends_at: string }[];
};
export type BusinessInfo = {
    name: string;
    address: string | null;
    whatsapp_url: string | null;
    map_url: string | null;
    hours: {
        weekday: number;
        starts_at: string | null;
        ends_at: string | null;
        is_closed: boolean;
    }[];
};
export type RegistrationInfo = {
    open: boolean;
    message: string;
    date: string;
    timezone: string;
    grace_minutes: number;
};
export type PublicCatalog = {
    services: PublicService[];
    barbers: PublicBarber[];
    business: BusinessInfo;
    registration: RegistrationInfo;
};
export type QueueFormProps = PublicCatalog & {
    qr: { code: string; label: string | null } | null;
    store_url: string;
    selected_service: number | null;
    selected_barber: number | null;
    recent_tickets: { id: string; url: string }[];
};
export type TicketPageProps = {
    ticket_id: string;
    urls: {
        status: string;
        access: string;
        secret: string;
        secret_ack: string;
        check_in: string;
        cancel: string;
    };
    timezone: string;
    grace_minutes: number;
};
