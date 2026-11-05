@extends('layouts.guest')
@section('content')
<div class="px-5 pt-5 pb-12">
    <x-ui.page-header title="{{ $game->title }}" subtitle="{{ $game->description ?? 'Kuis Pernikahan' }}" />

    @if ($attempt)
        <!-- Result View -->
        <div class="card p-6 mb-10">
            <p class="section-kicker mb-4">Hasil Game</p>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <p class="text-sm text-muted">Skor Anda</p>
                    <p class="font-serif text-4xl font-bold text-ink">{{ $attempt->score }} / {{ $attempt->total_questions }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-muted">Persentase</p>
                    <p class="font-serif text-4xl font-bold text-accent">{{ $attempt->percentage() }}%</p>
                </div>
            </div>
            <div class="h-2 bg-surface-2/60 rounded-full overflow-hidden">
                <div class="h-full bg-accent rounded-full" style="width: {{ $attempt->percentage() }}%"></div>
            </div>
        </div>

        <div class="card p-6 mb-10">
            <p class="section-kicker mb-4">Review Jawaban</p>
            <div class="space-y-4">
                @foreach ($game->questions as $question)
                    @php
                        $answer = $attempt->answers->firstWhere('game_question_id', $question->id);
                        $selectedOption = $answer ? $answer->gameOption : null;
                    @endphp
                    <div class="rounded-xl border border-line/60 bg-surface p-4">
                        <p class="font-semibold text-ink mb-2">{{ $question->question }}</p>
                        @foreach ($question->options as $option)
                            <div class="flex items-center gap-3 p-2 rounded-lg {{ $option->is_correct ? 'bg-success/10' : '' }} {{ $selectedOption && $selectedOption->id === $option->id && !$option->is_correct ? 'bg-danger/10' : '' }}">
                                <span class="flex shrink-0 h-6 w-6 items-center justify-center rounded-full border {{ $option->is_correct ? 'border-success' : ($selectedOption && $selectedOption->id === $option->id ? 'border-danger' : 'border-line') }} bg-white text-[10px] font-semibold">
                                    @if ($option->is_correct)
                                        <x-ui.icon name="check" class="h-4 w-4 text-success" />
                                    @elseif ($selectedOption && $selectedOption->id === $option->id)
                                        <x-ui.icon name="x" class="h-4 w-4 text-danger" />
                                    @endif
                                </span>
                                <span class="text-sm text-ink">{{ $option->label }}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex gap-3">
            <a href="{{ route('guest.invitation', $token) }}" class="btn-secondary flex-1 text-center">Kembali ke Undangan</a>
            <a href="{{ route('guest.interactions', $token) }}" class="btn-ghost flex-1 text-center">Interaksi Lain</a>
        </div>
    @elseif ($game->isPlayable())
        <!-- Play View -->
        <form method="POST" action="{{ route('guest.game.submit', [$token, $game]) }}" x-data="gameQuiz({{ $game->questions->toJson() }})" @submit.prevent="submitQuiz">
            @csrf
            <div class="card p-6 mb-10">
                <p class="section-kicker mb-4">Pertanyaan <span x-text="currentIndex + 1"></span> dari {{ $game->questions->count() }}</p>
                <div class="h-2 bg-surface-2/60 rounded-full overflow-hidden mb-6">
                    <div class="h-full bg-accent rounded-full transition-all duration-300" :style="'width: ' + ((currentIndex + 1) / questions.length * 100) + '%'"></div>
                </div>

                <template x-for="(question, index) in questions" :key="question.id">
                    <div x-show="currentIndex === index" x-transition>
                        <p class="font-semibold text-ink mb-4" x-text="question.question"></p>
                        <div class="space-y-3">
                            <template x-for="option in question.options" :key="option.id">
                                <label class="flex items-center gap-3 rounded-xl border border-line bg-surface p-3 cursor-pointer transition hover:bg-surface-2/60">
                                    <input type="radio" name="answers[{{ question.id }}]" :value="option.id" class="peer sr-only" required>
                                    <span class="text-sm text-muted peer-checked:text-ink" x-text="option.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="flex gap-3 mt-6">
                    <button type="button" class="btn-secondary flex-1" @click="prevQuestion" :disabled="currentIndex === 0">Sebelumnya</button>
                    <button type="button" class="btn-primary flex-1" @click="nextQuestion" x-show="currentIndex < questions.length - 1">Selanjutnya</button>
                    <button type="submit" class="btn-primary flex-1" x-show="currentIndex === questions.length - 1">Kirim Jawaban</button>
                </div>
            </div>
        </form>

        <script>
            function gameQuiz(questions) {
                return {
                    questions: questions,
                    currentIndex: 0,
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
        <x-ui.empty-state icon="game" title="Game Tidak Tersedia" description="Game ini belum aktif atau tidak memiliki pertanyaan.">
            <a href="{{ route('guest.invitation', $token) }}" class="btn-primary mt-4">Kembali ke Undangan</a>
        </x-ui.empty-state>
    @endif

    <p class="text-center text-[11px] text-faint mt-10">Dibuat dengan tenang oleh <span class="font-semibold text-ink">VOWSPACE</span></p>
</div>
</section>
</body>
</html>