<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::with('user', 'categories', 'images')->latest()->paginate(12);
        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',

            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id|distinct',

            'images' => 'nullable|array|max:5',
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif,avif,bmp', 'max:5120',],
        ]);

        $data['user_id'] = auth()->id();
        // Utilizzo un ciclo per controllare che lo slug sia unico, se non lo è aggiungo un numero progressivo
        $slug = Str::slug($data['name']);
        $originalSlug = $slug;
        $counter = 1;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $data['slug'] = $slug;
        // Separo le informazioni che non appartengono alla tabella products
        $categories = $data['categories'] ?? [];
        $images = $data['images'] ?? [];
        unset($data['categories'], $data['images']);

        $storedPaths = [];
        try {
            $product = DB::transaction(function () use ($data, $categories, $images, &$storedPaths) {
                $product = Product::create($data);
                $product->categories()->sync($categories);

                foreach ($images as $image) {
                    $path = $image->store('products', 'public');
                    $storedPaths[] = $path;

                    $product->images()->create(['path' => $path]);
                }
                return $product;
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($storedPaths);
            throw $e;
        }
        $product->load(['user', 'images', 'categories']);
        return response()->json($product, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $product->load(['user', 'categories', 'images']);
        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Product $product, Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',

            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id|distinct',

            'images' => 'nullable|array|max:5',
            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp,gif,avif,bmp',
                'max:5120',
            ],

            'remove_images' => 'nullable|array',
            'remove_images.*' => 'integer|distinct',
        ]);

        // Separo i dati destinati alle altre tabelle.
        $categoriesProvided = array_key_exists('categories', $data);
        $categories = $data['categories'] ?? [];
        $images = $data['images'] ?? [];
        $removeImages = $data['remove_images'] ?? [];

        unset(
            $data['categories'],
            $data['images'],
            $data['remove_images']
        );

        // Rigenero lo slug soltanto se cambia il nome.
        if ($data['name'] !== $product->name) {
            $originalSlug = Str::slug($data['name']);
            $slug = $originalSlug;
            $counter = 1;

            while (
                Product::withTrashed()
                ->where('slug', $slug)
                ->where('id', '!=', $product->id)
                ->exists()
            ) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $data['slug'] = $slug;
        }

        // Verifico che le immagini da eliminare appartengano al prodotto.
        foreach ($removeImages as $imageId) {
            if (!$product->images()->where('id', $imageId)->exists()) {
                return response()->json([
                    'message' =>
                    'Una delle immagini da eliminare non appartiene a questo prodotto.',
                ], 422);
            }
        }

        // Controllo il numero finale di immagini.
        $currentCount = $product->images()->count();
        $removeCount = count($removeImages);
        $newCount = count($images);

        if ($currentCount - $removeCount + $newCount > 5) {
            return response()->json([
                'message' =>
                'Un prodotto non può avere più di 5 immagini.',
            ], 422);
        }

        $storedPaths = [];
        $pathsToDelete = [];

        try {
            $product = DB::transaction(function () use (
                $product,
                $data,
                $categories,
                $categoriesProvided,
                $images,
                $removeImages,
                &$storedPaths,
                &$pathsToDelete,
            ) {
                // Registro i percorsi da eliminare dopo il commit.
                foreach ($removeImages as $imageId) {
                    $image = $product->images()->findOrFail($imageId);

                    $pathsToDelete[] = $image->path;
                    $image->delete();
                }

                $product->update($data);

                // Se le categorie non sono state inviate,
                // mantengo quelle esistenti.
                if ($categoriesProvided) {
                    $product->categories()->sync($categories);
                }

                // Salvo le nuove immagini.
                foreach ($images as $image) {
                    $path = $image->store('products', 'public');

                    if (!$path) {
                        throw new \RuntimeException(
                            'Impossibile salvare una delle immagini.'
                        );
                    }

                    $storedPaths[] = $path;

                    $product->images()->create([
                        'path' => $path,
                    ]);
                }

                return $product;
            });
        } catch (\Throwable $e) {
            // Il rollback riguarda il database.
            // Rimuovo separatamente i nuovi file già salvati.
            Storage::disk('public')->delete($storedPaths);

            throw $e;
        }

        // Elimino i vecchi file solo dopo il commit.
        Storage::disk('public')->delete($pathsToDelete);

        $product->load(['user', 'images', 'categories']);

        return response()->json($product);
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        if ($product->order_items()->exists()) {
            $product->delete(); // soft delete: ordini e immagini restano
        } else {
            $paths = $product->images->pluck('path')->all();

            $product->forceDelete(); // il cascade pulisce immagini, carrelli e categorie

            Storage::disk('public')->delete($paths); // i file solo dopo il commit
        }

        return response()->json(['message' => 'Prodotto eliminato correttamente.']);
    }

    // Recupero i prodotti collegati all'utente
    public function myProducts()
    {
        $products = auth()->user()->products()->with(['categories', 'images'])->latest()->get();
        return response()->json($products);
    }
}
