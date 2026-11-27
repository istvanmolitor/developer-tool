import type { Ref } from "vue";
import { onBeforeUnmount, ref } from "vue";

/**
 * Minimal shape of the browser's SpeechRecognition API. The DOM lib does not
 * ship these types, so we declare just what we use.
 */
type SpeechRecognitionResult = {
    0: { transcript: string };
    isFinal: boolean;
};

type SpeechRecognitionEvent = {
    resultIndex: number;
    results: {
        length: number;
        [index: number]: SpeechRecognitionResult;
    };
};

type SpeechRecognitionErrorEvent = {
    error: string;
};

type SpeechRecognitionInstance = {
    lang: string;
    continuous: boolean;
    interimResults: boolean;
    start(): void;
    stop(): void;
    abort(): void;
    onresult: ((event: SpeechRecognitionEvent) => void) | null;
    onerror: ((event: SpeechRecognitionErrorEvent) => void) | null;
    onend: (() => void) | null;
};

type SpeechRecognitionConstructor = new () => SpeechRecognitionInstance;

function getConstructor(): SpeechRecognitionConstructor | null {
    if (typeof window === "undefined") {
        return null;
    }

    const w = window as unknown as {
        SpeechRecognition?: SpeechRecognitionConstructor;
        webkitSpeechRecognition?: SpeechRecognitionConstructor;
    };

    return w.SpeechRecognition ?? w.webkitSpeechRecognition ?? null;
}

export type UseSpeechRecognitionOptions = {
    /** Recognition language (BCP-47), e.g. "hu-HU". */
    lang?: string;
    /** Called with each finalized transcript chunk (append this). */
    onFinal: (text: string) => void;
    /** Called with the live, not-yet-final transcript. */
    onInterim?: (text: string) => void;
};

export type UseSpeechRecognitionReturn = {
    /** Whether the browser supports speech recognition at all. */
    supported: boolean;
    /** Whether the microphone is currently listening. */
    listening: Ref<boolean>;
    /** Last error reported by the recognition engine, if any. */
    error: Ref<string | null>;
    start: () => void;
    stop: () => void;
    toggle: () => void;
};

/**
 * Dictation via the Web Speech API: speech gets transcribed to text. Final
 * chunks are handed to `onFinal`; the live guess, if any, to `onInterim`.
 */
export function useSpeechRecognition(
    options: UseSpeechRecognitionOptions,
): UseSpeechRecognitionReturn {
    const Constructor = getConstructor();
    const supported = Constructor !== null;

    const listening = ref(false);
    const error = ref<string | null>(null);

    let recognition: SpeechRecognitionInstance | null = null;

    function build(): SpeechRecognitionInstance | null {
        if (!Constructor) {
            return null;
        }

        const instance = new Constructor();
        instance.lang = options.lang ?? "hu-HU";
        instance.continuous = true;
        instance.interimResults = true;

        instance.onresult = (event) => {
            let final = "";
            let interim = "";

            for (let i = event.resultIndex; i < event.results.length; i++) {
                const result = event.results[i];
                const transcript = result[0].transcript;

                if (result.isFinal) {
                    final += transcript;
                } else {
                    interim += transcript;
                }
            }

            if (final) {
                options.onFinal(final.trim());
            }

            options.onInterim?.(interim.trim());
        };

        instance.onerror = (event) => {
            // "no-speech"/"aborted" are routine; surface the rest.
            if (event.error !== "no-speech" && event.error !== "aborted") {
                error.value = event.error;
            }

            listening.value = false;
        };

        instance.onend = () => {
            listening.value = false;
            options.onInterim?.("");
        };

        return instance;
    }

    function start(): void {
        if (!supported || listening.value) {
            return;
        }

        error.value = null;
        recognition = build();

        if (!recognition) {
            return;
        }

        try {
            recognition.start();
            listening.value = true;
        } catch {
            // start() throws if called while already starting; ignore.
            listening.value = false;
        }
    }

    function stop(): void {
        recognition?.stop();
        listening.value = false;
    }

    function toggle(): void {
        if (listening.value) {
            stop();
        } else {
            start();
        }
    }

    onBeforeUnmount(() => {
        recognition?.abort();
        recognition = null;
    });

    return { supported, listening, error, start, stop, toggle };
}
