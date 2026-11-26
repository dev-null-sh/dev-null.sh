import { QrCode } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

/**
 * Shows the profile's link as a QR code, so someone nearby can open it with a phone camera.
 */
export function ProfileQrButton({
    handle,
    href,
}: {
    handle: string;
    href: string;
}) {
    const [open, setOpen] = useState(false);
    const [url, setUrl] = useState<string | null>(null);

    // The absolute URL needs the browser's origin, so it is resolved when the dialog opens
    // and kept while it closes, so the code stays put through the exit animation.
    const onOpenChange = (next: boolean) => {
        if (next) {
            setUrl(new URL(href, window.location.origin).href);
        }
        setOpen(next);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={`Show a QR code for @${handle}'s profile`}
                    title="QR code"
                    data-test="profile-qr-button"
                >
                    <QrCode />
                </Button>
            </DialogTrigger>
            <DialogContent
                className="sm:max-w-sm"
                data-test="profile-qr-dialog"
            >
                <DialogHeader className="text-left">
                    <DialogTitle className="text-base font-semibold">
                        Scan @{handle}'s profile
                    </DialogTitle>
                    <DialogDescription>
                        Point a phone camera at the code to open the profile.
                    </DialogDescription>
                </DialogHeader>
                {url !== null && (
                    <div className="flex flex-col items-center gap-3">
                        {/* Always dark on white, even in dark mode, so every camera can read it. */}
                        <div className="rounded-md bg-white p-3">
                            <QRCodeSVG
                                value={url}
                                size={224}
                                bgColor="#ffffff"
                                fgColor="#000000"
                                title={url}
                                className="block size-56"
                            />
                        </div>
                        <p
                            className="w-full min-w-0 truncate text-center font-mono text-sm text-muted-foreground"
                            title={url}
                        >
                            {url.replace(/^https?:\/\//, '')}
                        </p>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
