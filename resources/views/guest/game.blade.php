@extends('layouts.guest')
@section('content')
<div class="px-4 pt-6 pb-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <x-ui.page-header title="{{ $game->title }}" subtitle="{{ $game->description ?? 'Kuis pernikahan' }}" />

        @if ($attempt)
            <!-- Result View -->
            <div class="card mt-6 p-6 sm:p-8">
                <h2 class="section-kicker mb-5">Hasil Game</h2>
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-muted">Skor Anda</p>
                        <p class="font-serif text-4xl font-bold text-ink">{{ $attempt->score }} / {{ $attempt->total_questions }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-muted">Persentase</p>
                        <p class="font-serif text-4xl font-bold text-accent">{{ $attempt->percentage() }}%</p>
                    </div>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-surface-2/60">
                    <div class="h-full rounded-full bg-accent" style="width: {{ $attempt->percentage() }}%"></div>
                </div>
            </div>

            <div class="card mt-8 p-6 sm:p-8">
                <h2 class="section-kicker mb-5">Review Jawaban</h2>
                <div class="space-y-4">
                    @foreach ($game->questions as $question)
                        @php
                            $answer = $attempt->answers->firstWhere('game_question_id', $question->id);
                            $selectedOption = $answer ? $answer->gameOption : null;
                        @endphp
                        <div class="rounded-xl border border-line/60 bg-surface p-4">
                            <p class="mb-2 font-semibold text-ink">{{ $question->question }}</p>
                            @foreach ($question->options as $option)
                                @php
                                    $isSelected = $selectedOption && $selectedOption->id === $option->id;
                                @endphp
                                <div class="flex items-center gap-3 rounded-lg p-2 {{ $option->is_correct ? 'bg-success/10' : ($isSelected ? 'bg-danger/10' : '') }}">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border bg-surface text-[10px] font-semibold {{ $option->is_correct ? 'border-success' : ($isSelected ? 'border-danger' : 'border-line') }}">
                                        @if ($option->is_correct)
                                            <x-ui.icon name="check" class="h-4 w-4 text-success" />
                                        @elseif ($isSelected)
                                            <x-ui.icon name="x" class="h-4 w-4 text-danger" />
                                        @endif
                                    </span>
                                    <span class="text-sm text-ink">{{ $option->label }}</span>
                                    @if ($isSelected)
                                        <span class="sr-only">Jawaban Anda</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-8 flex gap-3">
                <a href="{{ route('guest.invitation', $token) }}" class="btn-secondary flex-1 text-center">Kembali ke undangan</a>
                <a href="{{ route('guest.interactions', $token) }}" class="btn-ghost flex-1 text-center">Interaksi lain</a>
            </div>
        @elseif ($game->isPlayable())
            <!-- Play View -->
            <form method="POST" action="{{ route('guest.game.submit', [$token, $game]) }}" class="mt-6"
                  x-data="gameQuiz({{ $game->questions->toJson() }})" @submit.prevent="submitQuiz">
                @csrf
                <div class="card p-6 sm:p-8">
                    <p class="section-kicker mb-4">Pertanyaan <span x-text="currentIndex + 1"></span> dari {{ $game->questions->count() }}</p>
                    <div class="mb-6 h-2 overflow-hidden rounded-full bg-surface-2/60">
                        <div class="h-full rounded-full bg-accent transition-all duration-300" :style="'width: ' + ((currentIndex + 1) / questions.length * 100) + '%'"></div>
                    </div>

                    <template x-for="(question, index) in questions" :key="question.id">
                        <fieldset x-show="currentIndex === index" x-transition>
                            <legend class="mb-4 font-semibold text-ink" x-text="question.question"></legend>
                            <div class="space-y-3">
                                <template x-for="option in question.options" :key="option.id">
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line bg-surface p-3 transition hover:bg-surface-2/60 has-[:checked]:border-accent has-[:checked]:bg-blue-soft">
                                        <input type="radio" :name="'answers[' + question.id + ']'" :value="option.id" x-model="answers[question.id]" class="sr-only" required>
                                        <span class="text-sm text-muted has-[:checked]:text-ink" x-text="option.label"></span>
                                    </label>
                                </template>
                            </div>
                        </fieldset>
                    </template>

                    <div class="mt-6 flex gap-3">
                        <button type="button" class="btn-secondary flex-1" @click="prevQuestion" :disabled="currentIndex === 0">Sebelumnya</button>
                        <button type="button" class="btn-primary flex-1" @click="nextQuestion" x-show="currentIndex < questions.length - 1">Selanjutnya</button>
                        <button type="submit" class="btn-primary flex-1" x-show="currentIndex === questions.length - 1">Kirim jawaban</button>
                    </div>
                </div>
            </form>

            <script>
                function gameQuiz(questions) {
                    return {
                        questions: questions,
                        currentIndex: 0,
                        answers: {},
                        nextQuestion() {
                            if (this.currentIndex < this.questions.length - 1) {
                                this.currentIndex++;
                            }
                        },
                        prevQuestion() {
                            if (this.currentIndex > 0) {
                                this.currentIndex--;
                            }
                        },
                        submitQuiz() {
                            this.$el.submit();
                        }
                    }
                }
            </script>
        @else
            <div class="mt-6">
                <x-ui.empty-state icon="game" title="Game Tidak Tersedia" description="Game ini belum aktif atau tidak memiliki pertanyaan.">
                    <a href="{{ route('guest.invitation', $token) }}" class="btn-primary mt-4">Kembali ke undangan</a>
                </x-ui.empty-state>
            </div>
        @endif

        <p class="mt-10 text-center text-[11px] text-faint">
            Dibuat dengan tenang oleh <span class="font-semibold text-ink">VOWSPACE</span>
        </p>
    </div>
</div>
@endsection
