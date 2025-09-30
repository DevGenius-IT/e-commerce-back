<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmailTemplate extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'subject',
        'html_content',
        'plain_content',
        'variables',
        'category',
        'is_active',
        'created_by',
        'description',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
        'created_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($template) {
            if (!$template->slug) {
                $template->slug = $template->generateSlug();
            }
        });
    }

    /**
     * Get the campaigns using this template.
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'template_id');
    }

    /**
     * Scope to get active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get inactive templates.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Generate a unique slug.
     */
    public function generateSlug(): string
    {
        $slug = Str::slug($this->name);
        $count = 1;
        
        while (static::where('slug', $slug)->exists()) {
            $slug = Str::slug($this->name) . '-' . $count;
            $count++;
        }
        
        return $slug;
    }

    /**
     * Activate the template.
     */
    public function activate(): bool
    {
        $this->is_active = true;
        return $this->save();
    }

    /**
     * Deactivate the template.
     */
    public function deactivate(): bool
    {
        $this->is_active = false;
        return $this->save();
    }

    /**
     * Check if template is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if template is inactive.
     */
    public function isInactive(): bool
    {
        return !$this->is_active;
    }

    /**
     * Render template with variables.
     */
    public function render(array $variables = []): array
    {
        $htmlContent = $this->html_content;
        $plainContent = $this->plain_content;
        $subject = $this->subject;

        // Replace variables in content
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $htmlContent = str_replace($placeholder, $value, $htmlContent);
            $plainContent = str_replace($placeholder, $value, $plainContent);
            $subject = str_replace($placeholder, $value, $subject);
        }

        return [
            'subject' => $subject,
            'html_content' => $htmlContent,
            'plain_content' => $plainContent,
        ];
    }

    /**
     * Get available variables.
     */
    public function getAvailableVariables(): array
    {
        return $this->variables ?: [];
    }

    /**
     * Extract variables from content.
     */
    public function extractVariables(): array
    {
        $content = $this->html_content . ' ' . $this->plain_content . ' ' . $this->subject;
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);
        
        return array_unique($matches[1]);
    }

    /**
     * Validate template content.
     */
    public function validateContent(): array
    {
        $errors = [];
        
        // Check for required variables
        $extractedVars = $this->extractVariables();
        $definedVars = array_keys($this->getAvailableVariables());
        
        foreach ($extractedVars as $var) {
            if (!in_array($var, $definedVars)) {
                $errors[] = "Undefined variable: {$var}";
            }
        }
        
        // Check for basic HTML validity (simplified)
        if (!empty($this->html_content)) {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            if (!$dom->loadHTML($this->html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
                $errors[] = "Invalid HTML content";
            }
            libxml_clear_errors();
        }
        
        return $errors;
    }

    /**
     * Clone template with new name.
     */
    public function duplicate(string $newName): self
    {
        $duplicate = $this->replicate();
        $duplicate->name = $newName;
        $duplicate->slug = null; // Will be auto-generated
        $duplicate->save();
        
        return $duplicate;
    }

    /**
     * Get template usage statistics.
     */
    public function getUsageStatistics(): array
    {
        return [
            'total_campaigns' => $this->campaigns()->count(),
            'active_campaigns' => $this->campaigns()->whereIn('status', ['scheduled', 'sending'])->count(),
            'sent_campaigns' => $this->campaigns()->where('status', 'sent')->count(),
            'total_recipients' => $this->campaigns()->sum('total_recipients'),
            'last_used' => $this->campaigns()->latest('created_at')->first()?->created_at,
        ];
    }
}