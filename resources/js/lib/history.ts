export class HistoryStack<T> {
    private undoStack: T[] = [];
    private redoStack: T[] = [];
    private current: T;

    constructor(initial: T) {
        this.current = structuredClone(initial);
    }

    push(snapshot: T): void {
        this.undoStack.push(this.current);
        this.current = structuredClone(snapshot);
        this.redoStack = [];
    }

    undo(): T | null {
        const previous = this.undoStack.pop();
        if (previous === undefined) return null;
        this.redoStack.push(this.current);
        this.current = previous;
        return structuredClone(this.current);
    }

    redo(): T | null {
        const next = this.redoStack.pop();
        if (next === undefined) return null;
        this.undoStack.push(this.current);
        this.current = next;
        return structuredClone(this.current);
    }

    reset(snapshot: T): void {
        this.current = structuredClone(snapshot);
        this.undoStack = [];
        this.redoStack = [];
    }

    get canUndo(): boolean {
        return this.undoStack.length > 0;
    }

    get canRedo(): boolean {
        return this.redoStack.length > 0;
    }
}
